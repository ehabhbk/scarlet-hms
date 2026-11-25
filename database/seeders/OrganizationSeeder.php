<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $main = Branch::query()->updateOrCreate(
            ['code' => 'SCG'],
            [
                'name_ar' => 'مستشفى سكارلت العام',
                'name_en' => 'Scarlet General Hospital',
                'type' => 'hospital',
                'phone' => '+249 123 456 789',
                'email' => 'info@scarlet.care',
                'address' => 'شارع النيل، الخرطوم',
                'city' => 'الخرطوم',
                'timezone' => 'Africa/Khartoum',
                'is_active' => true,
            ]
        );

        $clinic = Branch::query()->updateOrCreate(
            ['code' => 'SCC'],
            [
                'name_ar' => 'مركز سكارلت التخصصي',
                'name_en' => 'Scarlet Specialized Center',
                'type' => 'clinic',
                'phone' => '+249 123 456 790',
                'email' => 'clinic@scarlet.care',
                'address' => 'الرياض، الخرطوم',
                'city' => 'الخرطوم',
                'timezone' => 'Africa/Khartoum',
                'is_active' => true,
            ]
        );

        $mainDepartments = [
            ['EMR', 'قسم الطوارئ', 'Emergency Department', 'clinical', 'الطابق الأرضي', 1],
            ['IM', 'الباطنية', 'Internal Medicine', 'clinical', 'الطابق الأول', 2],
            ['PED', 'الأطفال', 'Pediatrics', 'clinical', 'الطابق الثاني', 3],
            ['OBG', 'النساء والتوليد', 'Obstetrics & Gynecology', 'clinical', 'الطابق الثاني', 4],
            ['SUR', 'الجراحة العامة', 'General Surgery', 'clinical', 'الطابق الثالث', 5],
            ['ORTH', 'العظام', 'Orthopedics', 'clinical', 'الطابق الأول', 6],
            ['DERM', 'الجلدية', 'Dermatology', 'clinical', 'الطابق الأول', 7],
            ['ENT', 'الأنف والأذن والحنجرة', 'ENT', 'clinical', 'الطابق الثالث', 8],
            ['DENT', 'طب الأسنان', 'Dentistry', 'clinical', 'الطابق الأرضي', 9],
            ['OPH', 'طب العيون', 'Ophthalmology', 'clinical', 'الطابق الأول', 10],
            ['ICU', 'العناية المركزة', 'Intensive Care Unit', 'clinical', 'الطابق الثالث', 11],
            ['LAB', 'المختبر', 'Laboratory', 'diagnostics', 'الطابق الأرضي', 12],
            ['RAD', 'الأشعة', 'Radiology', 'diagnostics', 'الطابق الأرضي', 13],
            ['PHARM', 'الصيدلية', 'Pharmacy', 'support', 'الطابق الأرضي', 14],
            ['OPD', 'العيادات الخارجية', 'Outpatient Clinics', 'clinical', 'مبنى العيادات', 15],
        ];

        foreach ($mainDepartments as [$code, $nameAr, $nameEn, $type, $location, $sort]) {
            Department::query()->updateOrCreate(
                ['branch_id' => $main->id, 'code' => $code],
                [
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'type' => $type,
                    'location' => $location,
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            ['GEN', 'العيادات العامة', 'General Clinics', 'clinical', 1],
            ['LAB', 'مختبر المركز', 'Center Laboratory', 'diagnostics', 2],
        ] as [$code, $nameAr, $nameEn, $type, $sort]) {
            Department::query()->updateOrCreate(
                ['branch_id' => $clinic->id, 'code' => $code],
                [
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'type' => $type,
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Organization seeded: 2 branches, '.Department::query()->count().' departments.');
    }
}
