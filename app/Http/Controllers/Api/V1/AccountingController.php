<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalLine;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    public function __construct(private readonly SequenceService $sequences) {}

    public function accounts(Request $request): JsonResponse
    {
        $accounts = Account::query()
            ->with('parent:id,code,name_ar,name_en')
            ->orderBy('code')
            ->get();

        return ApiResponse::success($accounts);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounts,code'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $account = Account::create($data);

        return ApiResponse::created($account, 'Account created.');
    }

    public function journalEntries(Request $request): JsonResponse
    {
        $entries = JournalEntry::query()
            ->with(['postedBy:id,name', 'lines.account:id,code,name_ar,name_en'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($entries, $entries);
    }

    public function storeJournalEntry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'reference_type' => ['nullable', 'string', 'max:60'],
            'reference_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string'],
        ]);

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($data['lines'] as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return ApiResponse::error('Journal entry is not balanced.', null, 422);
        }

        $entry = DB::transaction(function () use ($data, $request) {
            $entry = JournalEntry::create([
                'entry_number' => $this->sequences->next('journal_entry', 'JE', 6),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'status' => 'posted',
                'posted_by' => $request->user()->id,
            ]);

            foreach ($data['lines'] as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry;
        });

        $entry->load(['lines.account', 'postedBy']);

        return ApiResponse::created($entry, 'Journal entry posted.');
    }

    public function trialBalance(): JsonResponse
    {
        $accounts = Account::query()->where('is_active', true)->orderBy('code')->get();
        $balances = JournalLine::query()
            ->select('account_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $rows = $accounts->map(function (Account $account) use ($balances) {
            $b = $balances->get($account->id);
            $debit = (float) ($b->total_debit ?? 0);
            $credit = (float) ($b->total_credit ?? 0);

            return [
                'account_id' => $account->id,
                'code' => $account->code,
                'name_ar' => $account->name_ar,
                'name_en' => $account->name_en,
                'type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $debit - $credit,
            ];
        });

        return ApiResponse::success($rows);
    }

    public function incomeStatement(): JsonResponse
    {
        $balances = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->select('accounts.type', DB::raw('SUM(journal_lines.debit) as total_debit'), DB::raw('SUM(journal_lines.credit) as total_credit'))
            ->whereIn('accounts.type', ['revenue', 'expense'])
            ->groupBy('accounts.type')
            ->get()
            ->keyBy('type');

        $revenue = (float) (($balances->get('revenue')->total_credit ?? 0) - ($balances->get('revenue')->total_debit ?? 0));
        $expense = (float) (($balances->get('expense')->total_debit ?? 0) - ($balances->get('expense')->total_credit ?? 0));

        return ApiResponse::success([
            'revenue' => $revenue,
            'expense' => $expense,
            'net_income' => $revenue - $expense,
        ]);
    }

    public function balanceSheet(): JsonResponse
    {
        $balances = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->select('accounts.type', DB::raw('SUM(journal_lines.debit) as total_debit'), DB::raw('SUM(journal_lines.credit) as total_credit'))
            ->whereIn('accounts.type', ['asset', 'liability', 'equity'])
            ->groupBy('accounts.type')
            ->get()
            ->keyBy('type');

        $assets = (float) (($balances->get('asset')->total_debit ?? 0) - ($balances->get('asset')->total_credit ?? 0));
        $liabilities = (float) (($balances->get('liability')->total_credit ?? 0) - ($balances->get('liability')->total_debit ?? 0));
        $equity = (float) (($balances->get('equity')->total_credit ?? 0) - ($balances->get('equity')->total_debit ?? 0));

        return ApiResponse::success([
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'balanced' => round($assets, 2) === round($liabilities + $equity, 2),
        ]);
    }

    public function ledger(Request $request, Account $account): JsonResponse
    {
        $lines = JournalLine::query()
            ->with(['entry:id,entry_number,entry_date,description'])
            ->where('account_id', $account->id)
            ->orderBy('id')
            ->get();

        return ApiResponse::success([
            'account' => $account,
            'lines' => $lines,
        ]);
    }
}
