<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Generic hospital chart of accounts. Codes follow the classic
     * 1=assets, 2=liabilities, 3=equity, 4=revenue, 5=expenses pattern.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name_ar' => 'الأصول', 'name_en' => 'Assets', 'type' => 'asset'],
            ['code' => '1010', 'name_ar' => 'الأصول المتداولة', 'name_en' => 'Current assets', 'type' => 'asset', 'parent' => '1000'],
            ['code' => '1011', 'name_ar' => 'النقد والصندوق', 'name_en' => 'Cash on hand', 'type' => 'asset', 'parent' => '1010', 'opening_balance' => 500000],
            ['code' => '1012', 'name_ar' => 'البنك', 'name_en' => 'Bank accounts', 'type' => 'asset', 'parent' => '1010', 'opening_balance' => 1200000],
            ['code' => '1013', 'name_ar' => 'المدينون والعملاء', 'name_en' => 'Accounts receivable', 'type' => 'asset', 'parent' => '1010'],
            ['code' => '1020', 'name_ar' => 'الأصول الثابتة', 'name_en' => 'Fixed assets', 'type' => 'asset', 'parent' => '1000'],
            ['code' => '1021', 'name_ar' => 'المعدات الطبية', 'name_en' => 'Medical equipment', 'type' => 'asset', 'parent' => '1020', 'opening_balance' => 800000],
            ['code' => '1022', 'name_ar' => 'الأثاث والتجهيزات', 'name_en' => 'Furniture and fixtures', 'type' => 'asset', 'parent' => '1020'],

            ['code' => '2000', 'name_ar' => 'الالتزامات', 'name_en' => 'Liabilities', 'type' => 'liability'],
            ['code' => '2010', 'name_ar' => 'الالتزامات المتداولة', 'name_en' => 'Current liabilities', 'type' => 'liability', 'parent' => '2000'],
            ['code' => '2011', 'name_ar' => 'الموردون', 'name_en' => 'Accounts payable', 'type' => 'liability', 'parent' => '2010'],
            ['code' => '2012', 'name_ar' => 'سلف العملاء', 'name_en' => 'Customer advances', 'type' => 'liability', 'parent' => '2010'],
            ['code' => '2020', 'name_ar' => 'التزامات طويلة الأجل', 'name_en' => 'Long-term liabilities', 'type' => 'liability', 'parent' => '2000'],

            ['code' => '3000', 'name_ar' => 'حقوق الملكية', 'name_en' => 'Equity', 'type' => 'equity'],
            ['code' => '3010', 'name_ar' => 'رأس المال', 'name_en' => 'Capital', 'type' => 'equity', 'parent' => '3000', 'opening_balance' => 2500000],
            ['code' => '3020', 'name_ar' => 'الاحتياطيات', 'name_en' => 'Reserves', 'type' => 'equity', 'parent' => '3000'],
            ['code' => '3030', 'name_ar' => 'الأرباح المبقاة', 'name_en' => 'Retained earnings', 'type' => 'equity', 'parent' => '3000'],

            ['code' => '4000', 'name_ar' => 'الإيرادات', 'name_en' => 'Revenue', 'type' => 'revenue'],
            ['code' => '4010', 'name_ar' => 'إيرادات العيادات الخارجية', 'name_en' => 'Outpatient revenue', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4020', 'name_ar' => 'إيرادات الإقامة', 'name_en' => 'Inpatient revenue', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4030', 'name_ar' => 'إيرادات المختبر', 'name_en' => 'Laboratory revenue', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4040', 'name_ar' => 'إيرادات الأشعة', 'name_en' => 'Radiology revenue', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4050', 'name_ar' => 'إيرادات الصيدلية', 'name_en' => 'Pharmacy revenue', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4060', 'name_ar' => 'إيرادات أخرى', 'name_en' => 'Other revenue', 'type' => 'revenue', 'parent' => '4000'],

            ['code' => '5000', 'name_ar' => 'المصروفات', 'name_en' => 'Expenses', 'type' => 'expense'],
            ['code' => '5010', 'name_ar' => 'رواتب وأجور', 'name_en' => 'Salaries and wages', 'type' => 'expense', 'parent' => '5000'],
            ['code' => '5020', 'name_ar' => 'أدوية ومستلزمات طبية', 'name_en' => 'Medical supplies', 'type' => 'expense', 'parent' => '5000'],
            ['code' => '5030', 'name_ar' => 'مصروفات المختبر', 'name_en' => 'Laboratory expenses', 'type' => 'expense', 'parent' => '5000'],
            ['code' => '5040', 'name_ar' => 'مصروفات الأشعة', 'name_en' => 'Radiology expenses', 'type' => 'expense', 'parent' => '5000'],
            ['code' => '5050', 'name_ar' => 'المرافق والصيانة', 'name_en' => 'Utilities and maintenance', 'type' => 'expense', 'parent' => '5000'],
            ['code' => '5060', 'name_ar' => 'الإيجار', 'name_en' => 'Rent', 'type' => 'expense', 'parent' => '5000'],
            ['code' => '5070', 'name_ar' => 'مصروفات إدارية', 'name_en' => 'Administrative expenses', 'type' => 'expense', 'parent' => '5000'],
            ['code' => '5080', 'name_ar' => 'مصروفات أخرى', 'name_en' => 'Other expenses', 'type' => 'expense', 'parent' => '5000'],
        ];

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(
                ['code' => $account['code']],
                [
                    'name_ar' => $account['name_ar'],
                    'name_en' => $account['name_en'],
                    'type' => $account['type'],
                    'opening_balance' => $account['opening_balance'] ?? 0,
                    'is_active' => true,
                ]
            );
        }

        foreach ($accounts as $account) {
            if (! isset($account['parent'])) {
                continue;
            }

            $child = Account::query()->where('code', $account['code'])->first();
            $parent = Account::query()->where('code', $account['parent'])->first();
            if ($child && $parent) {
                $child->update(['parent_id' => $parent->id]);
            }
        }
    }
}
