<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\Expense;
use App\Domains\Accounting\Models\JournalEntry;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    private const PAYMENT_ACCOUNTS = [
        'cash' => '1011',
        'bank' => '1012',
        'card' => '1012',
    ];

    public function __construct(private readonly SequenceService $sequences) {}

    private function paymentAccount(string $method): Account
    {
        $code = self::PAYMENT_ACCOUNTS[$method] ?? null;
        if (! $code) {
            throw ValidationException::withMessages([
                'payment_method' => ['Unsupported payment method.'],
            ]);
        }

        $account = Account::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'payment_method' => ['The payment account is not configured.'],
            ]);
        }

        return $account;
    }

    private function postedJournal(Expense $expense): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('reference_type', 'EXPENSE')
            ->where('reference_id', $expense->id)
            ->first();
    }

    private function postJournal(Expense $expense, ?int $userId): void
    {
        $paymentAccount = $this->paymentAccount($expense->payment_method);

        DB::transaction(function () use ($expense, $paymentAccount, $userId) {
            JournalEntry::query()
                ->where('reference_type', 'EXPENSE')
                ->where('reference_id', $expense->id)
                ->delete();

            $entry = JournalEntry::create([
                'entry_number' => $this->sequences->next('journal_entry', 'JE', 6),
                'entry_date' => $expense->expense_date,
                'description' => 'مصروف: '.$expense->category,
                'reference_type' => 'EXPENSE',
                'reference_id' => $expense->id,
                'status' => 'posted',
                'posted_by' => $userId,
            ]);

            $entry->lines()->create([
                'account_id' => $expense->account_id,
                'debit' => $expense->amount,
                'credit' => 0,
                'description' => $expense->description,
            ]);

            $entry->lines()->create([
                'account_id' => $paymentAccount->id,
                'debit' => 0,
                'credit' => $expense->amount,
                'description' => $expense->description,
            ]);
        });
    }

    private function loadRelations(Expense $expense): Expense
    {
        return $expense->load(['account:id,code,name_ar,name_en,type', 'creator:id,name']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Expense::query()->with(['account:id,code,name_ar,name_en,type', 'creator:id,name']);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('from')) {
            $query->where('expense_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('expense_date', '<=', $request->input('to'));
        }
        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('category', 'like', $search);
            });
        }

        $expenses = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return ApiResponse::paginated($expenses);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('type', 'expense')
                    ->where('is_active', true)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'in:cash,bank,card'],
            'is_approved' => ['nullable', 'boolean'],
        ]);

        $data['amount'] = round((float) $data['amount'], 2);
        $data['is_approved'] = $request->has('is_approved') ? $request->boolean('is_approved') : true;
        $data['created_by'] = $request->user()->id;

        $expense = DB::transaction(function () use ($data, $request) {
            $expense = Expense::create($data);
            $this->postJournal($expense, $request->user()->id);

            return $expense;
        });

        return ApiResponse::created($this->loadRelations($expense), 'Expense created and posted.');
    }

    public function show(Expense $expense): JsonResponse
    {
        return ApiResponse::success($this->loadRelations($expense));
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $data = $request->validate([
            'category' => ['sometimes', 'string', 'max:100'],
            'account_id' => [
                'sometimes',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('type', 'expense')
                    ->where('is_active', true)),
            ],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'expense_date' => ['sometimes', 'date'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'receipt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_method' => ['sometimes', 'in:cash,bank,card'],
            'is_approved' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('amount', $data)) {
            $data['amount'] = round((float) $data['amount'], 2);
        }

        $financialFields = ['category', 'account_id', 'amount', 'expense_date', 'description', 'payment_method'];
        $repost = (bool) count(array_intersect($financialFields, array_keys($data)));

        DB::transaction(function () use ($expense, $data, $repost, $request) {
            $expense->update($data);

            if ($repost) {
                $this->postJournal($expense->fresh(), $request->user()->id);
            }
        });

        return ApiResponse::success($this->loadRelations($expense->fresh()), 'Expense updated.');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        DB::transaction(function () use ($expense) {
            $this->postedJournal($expense)?->delete();
            $expense->delete();
        });

        return ApiResponse::success(null, 'Expense deleted.');
    }
}
