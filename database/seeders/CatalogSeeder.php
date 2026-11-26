<?php

namespace Database\Seeders;

use App\Domains\HumanResources\Models\Employee;
use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Laboratory\Models\LabDevice;
use App\Domains\Laboratory\Models\LabOrder;
use App\Domains\Laboratory\Models\LabOrderItem;
use App\Domains\Laboratory\Models\LabTest;
use App\Domains\Laboratory\Models\LabTestParameter;
use App\Domains\Patients\Models\Patient;
use App\Domains\Radiology\Models\RadiologyDevice;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Models\RadiologyStudy;
use App\Domains\Services\Models\Service;
use App\Support\Sequences\SequenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CatalogSeeder extends Seeder
{
    public function __construct(private readonly SequenceService $sequences) {}

    public function run(): void
    {
        $this->seedLabTests();
        $this->seedDevices();
        $this->seedRadiologyStudies();
        $this->seedRadiologyDevices();
        $this->seedServices();
        $this->seedStaff();
        $this->seedPendingLabSamples();
        $this->seedPendingRadiologyOrders();
    }

    private function seedLabTests(): void
    {
        $catalog = [
            'CBC' => [
                'name_ar' => 'صورة دم كاملة (CBC)',
                'name_en' => 'Complete Blood Count',
                'category' => 'hematology',
                'unit' => null,
                'reference_range' => null,
                'price' => 350,
                'parameters' => [
                    ['WBC', 'كريات الدم البيضاء', 'White Blood Cells', '10^9/L', '4.0 - 11.0'],
                    ['RBC', 'كريات الدم الحمراء', 'Red Blood Cells', '10^12/L', '4.5 - 5.5'],
                    ['HB', 'الهيموجلوبين', 'Hemoglobin', 'g/dL', '12.0 - 16.0'],
                    ['HCT', 'الهيماتوكريت', 'Hematocrit', '%', '36 - 48'],
                    ['MCV', 'الحجم المتوسط لكريات الدم', 'Mean Corpuscular Volume', 'fL', '80 - 100'],
                    ['MCH', 'الهيموجلوبين المتوسط', 'Mean Corpuscular Hemoglobin', 'pg', '27 - 33'],
                    ['MCHC', 'تركيز الهيموجلوبين', 'Mean Corpuscular Hemoglobin Concentration', 'g/dL', '32 - 36'],
                    ['PLT', 'الصفائح الدموية', 'Platelets', '10^9/L', '150 - 400'],
                    ['NEUT', 'الخلايا المتعادلة', 'Neutrophils', '%', '40 - 70'],
                    ['LYMPH', 'الخلايا اللمفاوية', 'Lymphocytes', '%', '20 - 45'],
                    ['MONO', 'الخلايا الأحادية', 'Monocytes', '%', '2 - 10'],
                    ['EOS', 'الخلايا الحمضية', 'Eosinophils', '%', '1 - 4'],
                    ['BASO', 'الخلايا القاعدية', 'Basophils', '%', '0 - 1'],
                ],
            ],
            'GLU' => [
                'name_ar' => 'سكر الدم ( Glucose )', 'name_en' => 'Blood Glucose', 'category' => 'chemistry',
                'unit' => 'mg/dL', 'reference_range' => '70 - 110', 'price' => 120, 'parameters' => [],
            ],
            'CREA' => [
                'name_ar' => 'الكرياتينين', 'name_en' => 'Creatinine', 'category' => 'chemistry',
                'unit' => 'mg/dL', 'reference_range' => '0.6 - 1.3', 'price' => 150, 'parameters' => [],
            ],
            'UREA' => [
                'name_ar' => 'اليوريا', 'name_en' => 'Urea', 'category' => 'chemistry',
                'unit' => 'mg/dL', 'reference_range' => '15 - 45', 'price' => 130, 'parameters' => [],
            ],
            'ALT' => [
                'name_ar' => 'ناقلة أمين الألانين (ALT)', 'name_en' => 'Alanine Aminotransferase', 'category' => 'chemistry',
                'unit' => 'U/L', 'reference_range' => '7 - 55', 'price' => 160, 'parameters' => [],
            ],
            'AST' => [
                'name_ar' => 'ناقلة أمين الأسبارتات (AST)', 'name_en' => 'Aspartate Aminotransferase', 'category' => 'chemistry',
                'unit' => 'U/L', 'reference_range' => '8 - 48', 'price' => 160, 'parameters' => [],
            ],
            'NA' => [
                'name_ar' => 'الصوديوم', 'name_en' => 'Sodium', 'category' => 'electrolytes',
                'unit' => 'mmol/L', 'reference_range' => '135 - 145', 'price' => 140, 'parameters' => [],
            ],
            'K' => [
                'name_ar' => 'البوتاسيوم', 'name_en' => 'Potassium', 'category' => 'electrolytes',
                'unit' => 'mmol/L', 'reference_range' => '3.5 - 5.1', 'price' => 140, 'parameters' => [],
            ],
            'CL' => [
                'name_ar' => 'الكلوريد', 'name_en' => 'Chloride', 'category' => 'electrolytes',
                'unit' => 'mmol/L', 'reference_range' => '98 - 107', 'price' => 140, 'parameters' => [],
            ],
            'TSH' => [
                'name_ar' => 'الهرمون المنبه للغدة الدرقية (TSH)', 'name_en' => 'Thyroid Stimulating Hormone', 'category' => 'hormones',
                'unit' => 'mIU/L', 'reference_range' => '0.4 - 4.0', 'price' => 450, 'parameters' => [],
            ],
            'T3' => [
                'name_ar' => 'ثالث يودوثيرونين (T3)', 'name_en' => 'Triiodothyronine', 'category' => 'hormones',
                'unit' => 'ng/mL', 'reference_range' => '0.8 - 2.0', 'price' => 400, 'parameters' => [],
            ],
            'T4' => [
                'name_ar' => 'ثيروكسين (T4)', 'name_en' => 'Thyroxine', 'category' => 'hormones',
                'unit' => 'µg/dL', 'reference_range' => '5.1 - 14.1', 'price' => 400, 'parameters' => [],
            ],
            'PROL' => [
                'name_ar' => 'البرلاكتين', 'name_en' => 'Prolactin', 'category' => 'hormones',
                'unit' => 'ng/mL', 'reference_range' => '2 - 25', 'price' => 480, 'parameters' => [],
            ],
            'UA' => [
                'name_ar' => 'تحليل البول', 'name_en' => 'Urinalysis', 'category' => 'urine',
                'unit' => null, 'reference_range' => null, 'price' => 180,
                'parameters' => [
                    ['PH', 'الرقم الهيدروجيني', 'pH', null, '4.5 - 8.0'],
                    ['PROTEIN', 'البروتين', 'Protein', null, 'Negative'],
                    ['GLUCOSE', 'الجلوكوز', 'Glucose', null, 'Negative'],
                    ['BLOOD', 'الدم', 'Blood', null, 'Negative'],
                ],
            ],
        ];

        foreach ($catalog as $code => $test) {
            $model = LabTest::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name_ar' => $test['name_ar'],
                    'name_en' => $test['name_en'],
                    'category' => $test['category'],
                    'unit' => $test['unit'],
                    'reference_range' => $test['reference_range'],
                    'price' => $test['price'],
                    'is_active' => true,
                ],
            );

            foreach ($test['parameters'] as $index => [$paramCode, $nameAr, $nameEn, $unit, $range]) {
                LabTestParameter::query()->firstOrCreate(
                    ['lab_test_id' => $model->id, 'code' => $paramCode],
                    [
                        'name_ar' => $nameAr,
                        'name_en' => $nameEn,
                        'unit' => $unit,
                        'reference_range' => $range,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }
    }

    private function seedDevices(): void
    {
        $devices = [
            ['CBC-01', 'محلل صورة الدم BC-3000', 'Mindray', 'BC-3000', 'HL7', ['CBC']],
            ['CHEM-01', 'محلل الكيمياء الحيوية', 'Hitachi', '902', 'ASTM', ['GLU', 'CREA', 'UREA', 'ALT', 'AST']],
            ['ISE-01', 'محلل الإلكترولايتات', 'Roche', 'ISE-900', 'HL7', ['NA', 'K', 'CL']],
            ['HORM-01', 'محلل الهرمونات', 'Roche', 'Cobas e411', 'HL7', ['TSH', 'T3', 'T4', 'PROL']],
            ['URINE-01', 'محلل البول', 'Sysmex', 'UF-100', 'ASTM', ['UA']],
        ];

        foreach ($devices as [$code, $name, $manufacturer, $model, $protocol, $testCodes]) {
            $device = LabDevice::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'manufacturer' => $manufacturer,
                    'model' => $model,
                    'protocol' => $protocol,
                    'status' => 'active',
                ],
            );

            $testIds = LabTest::query()->whereIn('code', $testCodes)->pluck('id');
            $device->tests()->syncWithoutDetaching($testIds);
        }
    }

    private function seedRadiologyStudies(): void
    {
        $studies = [
            ['XR-CHEST', 'أشعة سينية للصدر', 'Chest X-Ray', 'X-Ray', 220],
            ['XR-ABD', 'أشعة سينية للبطن', 'Abdominal X-Ray', 'X-Ray', 220],
            ['US-ABD', 'موجات فوق صوتية للبطن', 'Abdominal Ultrasound', 'Ultrasound', 380],
            ['US-OB', 'موجات فوق صوتية للحمل', 'Obstetric Ultrasound', 'Ultrasound', 420],
            ['CT-BRAIN', 'أشعة مقطعية للمخ', 'Brain CT', 'CT', 1200],
            ['MRI-BRAIN', 'رنين مغناطيسي للمخ', 'Brain MRI', 'MRI', 1800],
            ['ECG', 'تخطيط كهربية القلب', 'Electrocardiogram', 'ECG', 180],
            ['MAMMO', 'ماموغرافيا الثدي', 'Mammography', 'Mammography', 650],
        ];

        foreach ($studies as [$code, $nameAr, $nameEn, $modality, $price]) {
            RadiologyStudy::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'modality' => $modality,
                    'price' => $price,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedRadiologyDevices(): void
    {
        $devices = [
            ['XR-01', 'جهاز الأشعة السينية', 'Siemens', 'Multix Impact', 'HL7', 'X-Ray', ['XR-CHEST', 'XR-ABD']],
            ['US-01', 'جهاز الموجات فوق الصوتية', 'GE', 'Voluson E8', 'HL7', 'Ultrasound', ['US-ABD', 'US-OB']],
            ['CT-01', 'جهاز الأشعة المقطعية', 'Philips', 'Ingenuity Core', 'DICOM/HL7', 'CT', ['CT-BRAIN']],
            ['MRI-01', 'جهاز الرنين المغناطيسي', 'Siemens', 'Magnetom Vida', 'DICOM/HL7', 'MRI', ['MRI-BRAIN']],
        ];

        foreach ($devices as [$code, $name, $manufacturer, $model, $protocol, $modality, $studyCodes]) {
            $device = RadiologyDevice::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'manufacturer' => $manufacturer,
                    'model' => $model,
                    'protocol' => $protocol,
                    'modality' => $modality,
                    'status' => 'active',
                ],
            );

            $studyIds = RadiologyStudy::query()->whereIn('code', $studyCodes)->pluck('id');
            $device->studies()->syncWithoutDetaching($studyIds);
        }
    }

    private function seedServices(): void
    {
        $services = [
            ['CONSULT', 'كشف طبي عام', 'General Consultation', 'consultation', 300],
            ['FOLLOW', 'زيارة متابعة', 'Follow-up Visit', 'consultation', 200],
            ['DRESS', 'تغيير ضمادة', 'Dressing Change', 'nursing', 100],
            ['INJ', 'حقنة عضلية', 'Intramuscular Injection', 'nursing', 80],
            ['IV', 'محلول وريدي', 'IV Drip', 'nursing', 150],
        ];

        foreach ($services as [$code, $nameAr, $nameEn, $category, $price]) {
            Service::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'category' => $category,
                    'price' => $price,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedStaff(): void
    {
        $branch = Branch::query()->where('code', 'SCG')->first();
        $password = Hash::make('Scarlet@2026');

        $extraUsers = [
            ['metron', 'ممرضة رئيسة', 'head.nurse@scarlet.care', 'metron', 'ممرضة رئيسة'],
            ['sample', 'محضر عينات', 'sample@scarlet.care', 'sample_collector', 'محضر عينات'],
        ];

        foreach ($extraUsers as [$username, $name, $email, $roleName, $jobTitle]) {
            $user = User::query()->firstOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'locale' => 'ar',
                    'theme' => 'system',
                    'branch_id' => $branch?->id,
                    'job_title' => $jobTitle,
                    'is_active' => true,
                ],
            );

            $role = Role::query()->where('name', $roleName)->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }

        $employees = [
            ['EMP-001', 'doctor', 'د. سامر العلي', 'Internal Medicine', 'طبيب باطنة', 12000, 30],
            ['EMP-002', 'dr.mariam', 'د. مريم عبد الرحمن', 'Pediatrics', 'طبيب أطفال', 11000, 25],
            ['EMP-003', 'dr.othman', 'د. عثمان محمد', 'Surgery', 'طبيب جراح', 13000, 35],
            ['EMP-004', 'nurse', 'ممرضة/ممرض', 'Nursing', 'ممرض', 4500, 10],
            ['EMP-005', 'metron', 'الممرضة الرئيسة', 'Nursing', 'ممرضة رئيسة', 6000, 12],
            ['EMP-006', 'lab', 'مشرف المختبر', 'Laboratory', 'مشرف مختبر', 6500, 5],
            ['EMP-007', 'sample', 'محضر العينات', 'Laboratory', 'محضر عينات', 3500, 3],
            ['EMP-008', 'labtech', 'فني المختبر', 'Laboratory', 'فني مختبر', 5000, 5],
        ];

        foreach ($employees as [$number, $username, $name, $department, $title, $salary, $commission]) {
            $user = User::query()->where('username', $username)->first();
            Employee::query()->firstOrCreate(
                ['employee_number' => $number],
                [
                    'user_id' => $user?->id,
                    'name' => $name,
                    'department' => $department,
                    'job_title' => $title,
                    'basic_salary' => $salary,
                    'commission_percent' => $commission,
                    'hired_on' => '2025-01-01',
                    'status' => 'active',
                ],
            );
        }
    }

    private function seedPendingLabSamples(): void
    {
        if (LabOrderItem::query()->exists()) {
            return;
        }

        $branch = Branch::query()->where('code', 'SCG')->first();
        $patients = Patient::query()->orderBy('id')->limit(3)->get();
        $tests = LabTest::query()->whereIn('code', ['CBC', 'GLU', 'TSH'])->orderBy('id')->get();
        $doctor = User::query()->where('username', 'doctor')->first();

        if (! $branch || $patients->isEmpty() || $tests->isEmpty() || ! $doctor) {
            return;
        }

        foreach ($patients as $patient) {
            $order = LabOrder::query()->create([
                'order_number' => $this->sequences->next('lab_order', 'LAB', 6),
                'patient_id' => $patient->id,
                'branch_id' => $branch->id,
                'doctor_id' => $doctor->id,
                'status' => LabOrder::STATUS_ORDERED,
                'ordered_at' => now()->subMinutes(15),
                'notes' => 'طلب تجريبي لاختبار سير عمل المختبر.',
            ]);

            foreach ($tests as $test) {
                $order->items()->create([
                    'lab_test_id' => $test->id,
                    'status' => LabOrderItem::STATUS_PENDING,
                    'result_unit' => $test->unit,
                ]);
            }
        }
    }

    private function seedPendingRadiologyOrders(): void
    {
        if (RadiologyOrder::query()->exists()) {
            return;
        }

        $branch = Branch::query()->where('code', 'SCG')->first();
        $patients = Patient::query()->orderBy('id')->limit(4)->get();
        $studies = RadiologyStudy::query()->whereIn('code', ['XR-CHEST', 'US-ABD', 'CT-BRAIN', 'MRI-BRAIN'])->orderBy('id')->get();
        $doctor = User::query()->where('username', 'doctor')->first();

        if (! $branch || $patients->isEmpty() || $studies->isEmpty() || ! $doctor) {
            return;
        }

        foreach ($studies as $index => $study) {
            $patient = $patients[$index % $patients->count()];

            RadiologyOrder::query()->create([
                'order_number' => $this->sequences->next('radiology_order', 'RAD', 6),
                'patient_id' => $patient->id,
                'branch_id' => $branch->id,
                'doctor_id' => $doctor->id,
                'modality' => $study->modality,
                'study_description' => $study->name_ar,
                'radiology_study_id' => $study->id,
                'status' => RadiologyOrder::STATUS_ORDERED,
                'ordered_at' => now()->subMinutes(20 - ($index * 2)),
            ]);
        }
    }
}
