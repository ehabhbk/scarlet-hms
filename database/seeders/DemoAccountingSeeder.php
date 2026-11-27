<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\Expense;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Identity\Models\User;
use App\Support\Sequences\SequenceService;
use Illuminate\Database\Seeder;

class DemoAccountingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChartOfAccountsSeeder::class);

        $sequences = app(SequenceService::class);
        $userId = User::query()->first()?->id;

        // Demo journals are recreated on every seed so the sample figures stay consistent.
        JournalEntry::query()->where('reference_type', 'DEMO')->delete();

        $entries = [
            ['date' => now()->subDays(10)->toDateString(), 'description' => 'إيرادات عيادات خارجية', 'lines' => [
                ['code' => '1011', 'debit' => 25000, 'credit' => 0],
                ['code' => '4010', 'debit' => 0, 'credit' => 25000],
            ]],
            ['date' => now()->subDays(8)->toDateString(), 'description' => 'إيرادات المختبر', 'lines' => [
                ['code' => '1012', 'debit' => 24000, 'credit' => 0],
                ['code' => '4030', 'debit' => 0, 'credit' => 24000],
            ]],
            ['date' => now()->subDays(6)->toDateString(), 'description' => 'رواتب وأجور', 'lines' => [
                ['code' => '5010', 'debit' => 18000, 'credit' => 0],
                ['code' => '1011', 'debit' => 0, 'credit' => 18000],
            ]],
            ['date' => now()->subDays(5)->toDateString(), 'description' => 'إيجار المبنى', 'lines' => [
                ['code' => '5060', 'debit' => 9000, 'credit' => 0],
                ['code' => '1012', 'debit' => 0, 'credit' => 9000],
            ]],
            ['date' => now()->subDays(4)->toDateString(), 'description' => 'مستلزمات طبية من الموردين', 'lines' => [
                ['code' => '5020', 'debit' => 15000, 'credit' => 0],
                ['code' => '2011', 'debit' => 0, 'credit' => 15000],
            ]],
            ['date' => now()->subDays(3)->toDateString(), 'description' => 'إيرادات الصيدلية', 'lines' => [
                ['code' => '1011', 'debit' => 12000, 'credit' => 0],
                ['code' => '4050', 'debit' => 0, 'credit' => 12000],
            ]],
        ];

        foreach ($entries as $index => $entry) {
            $record = JournalEntry::create([
                'entry_number' => $sequences->next('journal_entry', 'JE', 6),
                'entry_date' => $entry['date'],
                'description' => $entry['description'],
                'reference_type' => 'DEMO',
                'reference_id' => $index + 1,
                'status' => 'posted',
                'posted_by' => $userId,
            ]);

            foreach ($entry['lines'] as $line) {
                $account = Account::query()->where('code', $line['code'])->first();
                if (! $account) {
                    continue;
                }

                $record->lines()->create([
                    'account_id' => $account->id,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $entry['description'],
                ]);
            }
        }

        $expenses = [
            ['category' => 'المرافق', 'account' => '5050', 'amount' => 3200, 'payment' => 'cash', 'days' => 3, 'description' => 'فاتورة الكهرباء والمياه'],
            ['category' => 'صيانة المعدات', 'account' => '5050', 'amount' => 2500, 'payment' => 'bank', 'days' => 2, 'description' => 'صيانة أجهزة المختبر'],
            ['category' => 'مصروفات إدارية', 'account' => '5070', 'amount' => 1300, 'payment' => 'cash', 'days' => 1, 'description' => 'مستلزمات مكتبية'],
        ];

        foreach ($expenses as $item) {
            $account = Account::query()->where('code', $item['account'])->first();
            if (! $account) {
                continue;
            }

            $expense = Expense::query()->firstOrCreate(
                ['category' => $item['category'], 'description' => $item['description']],
                [
                    'account_id' => $account->id,
                    'amount' => $item['amount'],
                    'expense_date' => now()->subDays($item['days'])->toDateString(),
                    'payment_method' => $item['payment'],
                    'is_approved' => true,
                    'created_by' => $userId,
                ]
            );

            $expense->update([
                'account_id' => $account->id,
                'amount' => $item['amount'],
                'expense_date' => now()->subDays($item['days'])->toDateString(),
                'payment_method' => $item['payment'],
                'is_approved' => true,
                'created_by' => $userId,
            ]);

            JournalEntry::query()
                ->where('reference_type', 'EXPENSE')
                ->where('reference_id', $expense->id)
                ->delete();

            $paymentCode = $item['payment'] === 'bank' ? '1012' : '1011';
            $paymentAccount = Account::query()->where('code', $paymentCode)->first();

            if ($paymentAccount) {
                $entry = JournalEntry::create([
                    'entry_number' => $sequences->next('journal_entry', 'JE', 6),
                    'entry_date' => $expense->expense_date,
                    'description' => 'مصروف: '.$expense->category,
                    'reference_type' => 'EXPENSE',
                    'reference_id' => $expense->id,
                    'status' => 'posted',
                    'posted_by' => $userId,
                ]);

                $entry->lines()->create([
                    'account_id' => $account->id,
                    'debit' => $item['amount'],
                    'credit' => 0,
                    'description' => $item['description'],
                ]);
                $entry->lines()->create([
                    'account_id' => $paymentAccount->id,
                    'debit' => 0,
                    'credit' => $item['amount'],
                    'description' => $item['description'],
                ]);
            }
        }
    }
}
