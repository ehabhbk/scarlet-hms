<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalLine;
use App\Domains\Billing\Models\Payment;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    public function __construct(private readonly SequenceService $sequences) {}

    private function accountName(Account $account): string
    {
        return $account->name_ar ?: $account->name_en ?: $account->code;
    }

    private function accountPayload(Account $account): array
    {
        return [
            'id' => $account->id,
            'parent_id' => $account->parent_id,
            'code' => $account->code,
            'name' => $this->accountName($account),
            'name_ar' => $account->name_ar,
            'name_en' => $account->name_en,
            'type' => $account->type,
            'opening_balance' => (float) $account->opening_balance,
            'balance' => (float) $account->opening_balance,
            'is_active' => (bool) $account->is_active,
            'notes' => $account->notes,
        ];
    }

    private function movementTotals(Account $account, ?string $from = null, ?string $to = null): array
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id);

        if ($from) {
            $query->where('journal_entries.entry_date', '>=', $from);
        }
        if ($to) {
            $query->where('journal_entries.entry_date', '<=', $to);
        }

        $totals = $query->selectRaw(
            'COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit'
        )->first();

        return [
            'debit' => (float) ($totals->total_debit ?? 0),
            'credit' => (float) ($totals->total_credit ?? 0),
        ];
    }

    private function balanceFor(Account $account, ?string $from = null, ?string $to = null): float
    {
        $totals = $this->movementTotals($account, $from, $to);
        $net = $totals['debit'] - $totals['credit'];

        // Assets and expenses increase with debits; the other account types increase with credits.
        if (in_array($account->type, ['asset', 'expense'], true)) {
            return round((float) $account->opening_balance + $net, 2);
        }

        return round((float) $account->opening_balance - $net, 2);
    }

    /**
     * Leaf accounts only — parent accounts are roll-ups and would double-count
     * balances in the income statement and balance sheet.
     */
    private function leafAccounts(string $type)
    {
        $parentIds = Account::query()
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->all();

        return Account::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->whereNotIn('id', $parentIds)
            ->orderBy('code')
            ->get();
    }

    private function journalLinePayload(JournalLine $line): array
    {
        $account = $line->account;

        return [
            'id' => $line->id,
            'journal_entry_id' => $line->journal_entry_id,
            'account_id' => $line->account_id,
            'account' => $account ? [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $this->accountName($account),
                'name_ar' => $account->name_ar,
                'name_en' => $account->name_en,
            ] : null,
            'account_code' => $account?->code,
            'account_name' => $account ? $this->accountName($account) : null,
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
            'description' => $line->description,
        ];
    }

    private function journalEntryPayload(JournalEntry $entry): array
    {
        $reference = $entry->reference_type
            ? $entry->reference_type.($entry->reference_id ? '-'.$entry->reference_id : '')
            : null;

        return [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'entry_date' => $entry->entry_date?->toDateString(),
            'description' => $entry->description,
            'reference' => $reference,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'status' => $entry->status,
            'posted_by' => $entry->posted_by,
            'creator' => $entry->postedBy ? [
                'id' => $entry->postedBy->id,
                'name' => $entry->postedBy->name,
            ] : null,
            'lines' => $entry->lines->map(fn (JournalLine $line) => $this->journalLinePayload($line))->values()->all(),
            'created_at' => $entry->created_at,
        ];
    }

    private function loadEntry(JournalEntry $entry): JournalEntry
    {
        return $entry->load(['lines.account', 'postedBy:id,name']);
    }

    private function accountIdsWithChildren(int $accountId): array
    {
        $accounts = Account::query()->get(['id', 'parent_id']);
        $ids = [$accountId];

        $descendants = function (int $parentId) use (&$descendants, $accounts): array {
            $found = [];
            foreach ($accounts->where('parent_id', $parentId) as $child) {
                $found[] = (int) $child->id;
                $found = array_merge($found, $descendants((int) $child->id));
            }

            return $found;
        };

        return array_values(array_unique(array_merge($ids, $descendants($accountId))));
    }

    public function accounts(Request $request): JsonResponse
    {
        $accounts = Account::query()
            ->with('parent:id,code,name_ar,name_en')
            ->orderBy('code')
            ->get();

        return ApiResponse::success($accounts->map(fn (Account $account) => $this->accountPayload($account)));
    }

    public function chartOfAccounts(Request $request): JsonResponse
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $accounts = Account::query()
            ->with('parent:id,code,name_ar,name_en')
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($from, $to) {
                $payload = $this->accountPayload($account);
                $payload['balance'] = $this->balanceFor($account, $from, $to);

                return $payload;
            });

        return ApiResponse::success(['accounts' => $accounts]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounts,code'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'opening_balance' => ['nullable', 'numeric'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $account = Account::create($data);

        return ApiResponse::created($this->accountPayload($account), 'Account created.');
    }

    public function updateAccount(Request $request, Account $account): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('accounts', 'code')->ignore($account->id)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'opening_balance' => ['nullable', 'numeric'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id', 'different:id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === (int) $account->id) {
            return ApiResponse::error('An account cannot be its own parent.', null, 422);
        }

        $account->update($data);

        return ApiResponse::success($this->accountPayload($account->fresh()), 'Account updated.');
    }

    public function destroyAccount(Account $account): JsonResponse
    {
        $account->delete();

        return ApiResponse::success(null, 'Account deleted.');
    }

    public function journalEntries(Request $request): JsonResponse
    {
        $query = JournalEntry::query()
            ->with(['postedBy:id,name', 'lines.account:id,code,name_ar,name_en']);

        if ($request->filled('from')) {
            $query->where('entry_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('entry_date', '<=', $request->input('to'));
        }
        if ($request->filled('entry_number')) {
            $query->where('entry_number', 'like', '%'.$request->input('entry_number').'%');
        }
        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'like', $search)
                    ->orWhere('reference_type', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        $entries = $query
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 200))
            ->through(fn (JournalEntry $entry) => $this->journalEntryPayload($entry));

        return ApiResponse::paginated($entries);
    }

    public function showJournalEntry(JournalEntry $journal_entry): JsonResponse
    {
        return ApiResponse::success($this->journalEntryPayload($this->loadEntry($journal_entry)));
    }

    public function storeJournalEntry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:60'],
            'reference_type' => ['nullable', 'string', 'max:60'],
            'reference_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($data['lines'] as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }
        if (abs(round($totalDebit - $totalCredit, 2)) > 0.001) {
            return ApiResponse::error('Journal entry is not balanced.', null, 422);
        }

        $accountIds = array_column($data['lines'], 'account_id');
        if (Account::query()->whereIn('id', $accountIds)->where('is_active', true)->count() !== count($accountIds)) {
            return ApiResponse::error('One or more accounts are inactive or invalid.', null, 422);
        }

        $referenceType = $data['reference_type'] ?? $data['reference'] ?? null;

        $entry = DB::transaction(function () use ($data, $request, $referenceType) {
            $entry = JournalEntry::create([
                'entry_number' => $this->sequences->next('journal_entry', 'JE', 6),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'] ?? '',
                'reference_type' => $referenceType,
                'reference_id' => $data['reference_id'] ?? null,
                'status' => 'posted',
                'posted_by' => $request->user()->id,
            ]);

            foreach ($data['lines'] as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry;
        });

        return ApiResponse::created($this->journalEntryPayload($this->loadEntry($entry)), 'Journal entry posted.');
    }

    public function updateJournalEntry(Request $request, JournalEntry $journal_entry): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:60'],
            'reference_type' => ['nullable', 'string', 'max:60'],
            'reference_id' => ['nullable', 'integer'],
            'lines' => ['sometimes', 'array', 'min:2'],
            'lines.*.account_id' => ['required_with:lines', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['lines'])) {
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            foreach ($data['lines'] as $line) {
                $totalDebit += (float) ($line['debit'] ?? 0);
                $totalCredit += (float) ($line['credit'] ?? 0);
            }
            if (abs(round($totalDebit - $totalCredit, 2)) > 0.001) {
                return ApiResponse::error('Journal entry is not balanced.', null, 422);
            }

            $accountIds = array_column($data['lines'], 'account_id');
            if (Account::query()->whereIn('id', $accountIds)->where('is_active', true)->count() !== count($accountIds)) {
                return ApiResponse::error('One or more accounts are inactive or invalid.', null, 422);
            }
        }

        $referenceType = array_key_exists('reference_type', $data)
            ? $data['reference_type']
            : (array_key_exists('reference', $data) ? $data['reference'] : $journal_entry->reference_type);

        DB::transaction(function () use ($journal_entry, $data, $referenceType) {
            $journal_entry->update(array_filter([
                'entry_date' => $data['entry_date'] ?? $journal_entry->entry_date,
                'description' => $data['description'] ?? $journal_entry->description,
                'reference_type' => $referenceType,
                'reference_id' => $data['reference_id'] ?? $journal_entry->reference_id,
            ], static fn ($value) => $value !== null));

            if (isset($data['lines'])) {
                $journal_entry->lines()->delete();
                foreach ($data['lines'] as $line) {
                    $journal_entry->lines()->create([
                        'account_id' => $line['account_id'],
                        'debit' => round((float) ($line['debit'] ?? 0), 2),
                        'credit' => round((float) ($line['credit'] ?? 0), 2),
                        'description' => $line['description'] ?? null,
                    ]);
                }
            }
        });

        return ApiResponse::success(
            $this->journalEntryPayload($this->loadEntry($journal_entry->fresh())),
            'Journal entry updated.'
        );
    }

    public function destroyJournalEntry(JournalEntry $journal_entry): JsonResponse
    {
        DB::transaction(function () use ($journal_entry) {
            $journal_entry->lines()->delete();
            $journal_entry->delete();
        });

        return ApiResponse::success(null, 'Journal entry deleted.');
    }

    public function trialBalance(Request $request): JsonResponse
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $balances = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($from, fn ($q) => $q->where('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('journal_entries.entry_date', '<=', $to))
            ->select(
                'journal_lines.account_id',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $rows = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($balances) {
                $balance = $balances->get($account->id);
                $debit = (float) ($balance->total_debit ?? 0);
                $credit = (float) ($balance->total_credit ?? 0);

                return [
                    'account_id' => $account->id,
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $this->accountName($account),
                    'name_ar' => $account->name_ar,
                    'name_en' => $account->name_en,
                    'type' => $account->type,
                    'opening_balance' => (float) $account->opening_balance,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => round($debit - $credit, 2),
                ];
            });

        return ApiResponse::success($rows);
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $toRows = function (string $type) use ($from, $to) {
            return $this->leafAccounts($type)
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $this->accountName($account),
                    'name_ar' => $account->name_ar,
                    'name_en' => $account->name_en,
                    'amount' => $this->balanceFor($account, $from, $to),
                ]);
        };

        $revenue = $toRows('revenue');
        $expense = $toRows('expense');
        $totalRevenue = (float) $revenue->sum('amount');
        $totalExpense = (float) $expense->sum('amount');

        return ApiResponse::success([
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'expense' => $expense,
            'total_revenue' => round($totalRevenue, 2),
            'total_expense' => round($totalExpense, 2),
            'net_income' => round($totalRevenue - $totalExpense, 2),
        ]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $asOf = $request->string('as_of')->toString() ?: now()->toDateString();

        $toRows = function (string $type) use ($asOf) {
            return $this->leafAccounts($type)
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $this->accountName($account),
                    'name_ar' => $account->name_ar,
                    'name_en' => $account->name_en,
                    'amount' => $this->balanceFor($account, null, $asOf),
                ]);
        };

        $assets = $toRows('asset');
        $liabilities = $toRows('liability');
        $equity = $toRows('equity');

        // Current-period result belongs to equity so the balance sheet stays balanced.
        $revenue = (float) $this->leafAccounts('revenue')
            ->sum(fn (Account $account) => $this->balanceFor($account, null, $asOf));
        $expense = (float) $this->leafAccounts('expense')
            ->sum(fn (Account $account) => $this->balanceFor($account, null, $asOf));
        $periodResult = round($revenue - $expense, 2);

        if (abs($periodResult) >= 0.005) {
            $equity->push([
                'id' => 0,
                'code' => '3999',
                'name' => $periodResult >= 0 ? 'نتيجة الفترة' : 'خسارة الفترة',
                'name_ar' => $periodResult >= 0 ? 'نتيجة الفترة' : 'خسارة الفترة',
                'name_en' => $periodResult >= 0 ? 'Current period result' : 'Current period loss',
                'amount' => $periodResult,
            ]);
        }

        $totalAssets = (float) $assets->sum('amount');
        $totalLiabilities = (float) $liabilities->sum('amount');
        $totalEquity = (float) $equity->sum('amount');

        return ApiResponse::success([
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'total_liabilities_equity' => round($totalLiabilities + $totalEquity, 2),
        ]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $accountId = $request->input('account_id');
        if (! $accountId) {
            return ApiResponse::error('account_id is required.', null, 422);
        }

        $account = Account::query()->findOrFail($accountId);
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;
        $includeChildren = $request->boolean('include_children');

        $accountIds = $includeChildren
            ? $this->accountIdsWithChildren((int) $account->id)
            : [(int) $account->id];
        $accountsById = Account::query()->whereIn('id', $accountIds)->get()->keyBy('id');

        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $accountIds)
            ->select(
                'journal_lines.*',
                'journal_entries.entry_number',
                'journal_entries.entry_date',
                'journal_entries.description as entry_description',
                'journal_entries.reference_type',
                'journal_entries.reference_id'
            )
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id');

        if ($from) {
            $query->where('journal_entries.entry_date', '>=', $from);
        }
        if ($to) {
            $query->where('journal_entries.entry_date', '<=', $to);
        }
        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('journal_entries.entry_number', 'like', $search)
                    ->orWhere('journal_entries.description', 'like', $search)
                    ->orWhere('journal_entries.reference_type', 'like', $search)
                    ->orWhere('journal_lines.description', 'like', $search);
            });
        }

        $lines = $query->get();

        $running = 0.0;
        $perAccount = [];
        foreach ($accountIds as $id) {
            $item = $accountsById->get($id);
            if (! $item) {
                continue;
            }
            $signedOpening = in_array($item->type, ['asset', 'expense'], true)
                ? (float) $item->opening_balance
                : -(float) $item->opening_balance;
            $running += $signedOpening;
            $perAccount[$id] = [
                'id' => $id,
                'code' => $item->code,
                'name' => $this->accountName($item),
                'debit' => 0.0,
                'credit' => 0.0,
                'opening' => (float) $item->opening_balance,
                'closing' => $signedOpening,
            ];
        }
        $openingBalance = $running;

        $rows = [];
        foreach ($lines as $line) {
            $signed = (float) $line->debit - (float) $line->credit;
            $running += $signed;
            $account = $accountsById->get((int) $line->account_id);
            $id = (int) $line->account_id;

            if (isset($perAccount[$id])) {
                $perAccount[$id]['debit'] += (float) $line->debit;
                $perAccount[$id]['credit'] += (float) $line->credit;
                $perAccount[$id]['closing'] += $signed;
            }

            $reference = $line->reference_type
                ? $line->reference_type.($line->reference_id ? '-'.$line->reference_id : '')
                : null;

            $rows[] = [
                'date' => substr((string) $line->entry_date, 0, 10),
                'entry_number' => $line->entry_number,
                'reference' => $reference,
                'account_id' => $id,
                'account_code' => $account?->code,
                'account_name' => $account ? $this->accountName($account) : null,
                'description' => $line->description ?: $line->entry_description,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'balance' => round($running, 2),
            ];
        }

        return ApiResponse::success([
            'account' => $this->accountPayload($account),
            'include_children' => $includeChildren,
            'account_ids' => $accountIds,
            'opening_balance' => round($openingBalance, 2),
            'lines' => $rows,
            'closing_balance' => round($running, 2),
            'total_debit' => round(array_sum(array_column($rows, 'debit')), 2),
            'total_credit' => round(array_sum(array_column($rows, 'credit')), 2),
            'per_account' => array_values($perAccount),
        ]);
    }

    public function accountLedger(Request $request, Account $account): JsonResponse
    {
        $request->merge(['account_id' => $account->id]);

        return $this->ledger($request);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $sumType = function (string $type) use ($from, $to): float {
            return (float) $this->leafAccounts($type)
                ->sum(fn (Account $account) => $this->balanceFor($account, $from, $to));
        };

        $balanceOfCode = function (string $code) use ($from, $to): float {
            $account = Account::query()->where('code', $code)->first();

            return $account ? $this->balanceFor($account, $from, $to) : 0.0;
        };

        $revenue = $sumType('revenue');
        $expense = $sumType('expense');
        $purchase = $balanceOfCode('5020');
        $wages = $balanceOfCode('5010');

        return ApiResponse::success([
            'revenue' => round($revenue, 2),
            'expense' => round($expense, 2),
            'purchase' => round($purchase, 2),
            'wages' => round($wages, 2),
            'profit' => round($revenue - $expense, 2),
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $rows = Payment::query()
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to.' 23:59:59'))
            ->select(
                'method',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->method,
                'total' => (float) $row->total,
                'transactions' => (int) $row->transactions,
            ]);

        return ApiResponse::success(['methods' => $rows]);
    }
}
