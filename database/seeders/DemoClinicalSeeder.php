<?php

namespace Database\Seeders;

use App\Domains\Encounters\Services\EncounterService;
use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Services\AppointmentService;
use App\Domains\Scheduling\Services\QueueService;
use App\Support\Sequences\SequenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoClinicalSeeder extends Seeder
{
    public function run(): void
    {
        if (Patient::query()->exists()) {
            $this->command?->warn('Demo clinical data skipped: patients already exist.');

            return;
        }

        $branch = Branch::query()->where('code', 'SCG')->firstOrFail();
        $department = fn (string $code) => Department::query()
            ->where('branch_id', $branch->id)
            ->where('code', $code)
            ->value('id');

        $reception = User::query()->where('username', 'reception')->firstOrFail();
        $doctor = User::query()->where('username', 'doctor')->firstOrFail();
        $pediatrician = User::query()->where('username', 'dr.mariam')->firstOrFail();
        $nurse = User::query()->where('username', 'nurse')->firstOrFail();

        $sequences = app(SequenceService::class);
        $appointments = app(AppointmentService::class);
        $queues = app(QueueService::class);
        $encounters = app(EncounterService::class);

        $records = [
            [
                'first_name_ar' => 'أحمد', 'last_name_ar' => 'محمد علي',
                'first_name_en' => 'Ahmed', 'last_name_en' => 'Mohamed Ali',
                'gender' => 'male', 'date_of_birth' => '1985-04-12',
                'national_id' => '11010101011', 'phone' => '0912345678',
                'blood_type' => 'O+', 'marital_status' => 'married', 'city' => 'الخرطوم',
                'allergy' => ['البنسلين', 'طفح جلدي وتورم', 'severe'],
                'condition' => ['chronic', 'I10', 'ارتفاع ضغط الدم', 'Hypertension', '2019-05-10'],
            ],
            [
                'first_name_ar' => 'فاطمة', 'last_name_ar' => 'عبد الرحمن',
                'first_name_en' => 'Fatima', 'last_name_en' => 'Abdelrahman',
                'gender' => 'female', 'date_of_birth' => '1992-09-23',
                'national_id' => '11010101012', 'phone' => '0923456789',
                'blood_type' => 'A+', 'marital_status' => 'married', 'city' => 'الخرطوم',
                'condition' => ['chronic', 'J45', 'ربو شعبي', 'Bronchial asthma', '2010-02-15'],
            ],
            [
                'first_name_ar' => 'محمد', 'last_name_ar' => 'يوسف إبراهيم',
                'first_name_en' => 'Mohamed', 'last_name_en' => 'Yousif Ibrahim',
                'gender' => 'male', 'date_of_birth' => '1978-01-05',
                'national_id' => '11010101013', 'phone' => '0934567890',
                'blood_type' => 'B+', 'marital_status' => 'married', 'city' => 'أم درمان',
                'allergy' => ['الأسبرين', 'حكة جلدية', 'moderate'],
                'condition' => ['chronic', 'E11', 'داء السكري من النوع الثاني', 'Type 2 diabetes', '2016-08-20'],
            ],
            [
                'first_name_ar' => 'مريم', 'last_name_ar' => 'عثمان',
                'first_name_en' => 'Mariam', 'last_name_en' => 'Osman',
                'gender' => 'female', 'date_of_birth' => '2001-06-18',
                'national_id' => '11010101014', 'phone' => '0945678901',
                'blood_type' => 'AB+', 'marital_status' => 'single', 'city' => 'الخرطوم',
            ],
            [
                'first_name_ar' => 'خديجة', 'last_name_ar' => 'علي',
                'first_name_en' => 'Khadija', 'last_name_en' => 'Ali',
                'gender' => 'female', 'date_of_birth' => '1966-11-30',
                'national_id' => '11010101015', 'phone' => '0956789012',
                'blood_type' => 'O-', 'marital_status' => 'widowed', 'city' => 'الخرطوم',
                'condition' => ['chronic', 'N18', 'مرض الكلى المزمن', 'Chronic kidney disease', '2021-01-11'],
            ],
            [
                'first_name_ar' => 'عمر', 'last_name_ar' => 'حسن',
                'first_name_en' => 'Omar', 'last_name_en' => 'Hassan',
                'gender' => 'male', 'date_of_birth' => '2015-03-14',
                'national_id' => '11010101016', 'phone' => '0967890123',
                'blood_type' => 'A-', 'marital_status' => 'single', 'city' => 'الخرطوم',
                'allergy' => ['الفول السوداني', 'تورم في الوجه', 'life_threatening'],
            ],
            [
                'first_name_ar' => 'سلمى', 'last_name_ar' => 'إسماعيل',
                'first_name_en' => 'Salma', 'last_name_en' => 'Ismail',
                'gender' => 'female', 'date_of_birth' => '1988-07-07',
                'national_id' => '11010101017', 'phone' => '0978901234',
                'blood_type' => 'B-', 'marital_status' => 'married', 'city' => 'الخرطوم',
            ],
            [
                'first_name_ar' => 'ياسر', 'last_name_ar' => 'الطيب',
                'first_name_en' => 'Yasir', 'last_name_en' => 'Eltayeb',
                'gender' => 'male', 'date_of_birth' => '1995-12-25',
                'national_id' => '11010101018', 'phone' => '0989012345',
                'blood_type' => 'O+', 'marital_status' => 'single', 'city' => 'الخرطوم',
            ],
            [
                'first_name_ar' => 'آية', 'last_name_ar' => 'عبد الله',
                'first_name_en' => 'Aya', 'last_name_en' => 'Abdalla',
                'gender' => 'female', 'date_of_birth' => '2010-05-02',
                'national_id' => '11010101019', 'phone' => '0990123456',
                'blood_type' => 'A+', 'marital_status' => 'single', 'city' => 'الخرطوم',
            ],
            [
                'first_name_ar' => 'إبراهيم', 'last_name_ar' => 'موسى',
                'first_name_en' => 'Ibrahim', 'last_name_en' => 'Musa',
                'gender' => 'male', 'date_of_birth' => '1955-08-19',
                'national_id' => '11010101020', 'phone' => '0911223344',
                'blood_type' => 'AB-', 'marital_status' => 'married', 'city' => 'الخرطوم',
                'allergy' => ['السلفا', 'طفح جلدي', 'mild'],
            ],
            [
                'first_name_ar' => 'هبة', 'last_name_ar' => 'مصطفى',
                'first_name_en' => 'Heba', 'last_name_en' => 'Mustafa',
                'gender' => 'female', 'date_of_birth' => '1999-02-11',
                'national_id' => '11010101021', 'phone' => '0922334455',
                'blood_type' => 'A+', 'marital_status' => 'single', 'city' => 'الخرطوم',
            ],
            [
                'first_name_ar' => 'طارق', 'last_name_ar' => 'النور',
                'first_name_en' => 'Tarek', 'last_name_en' => 'Elnour',
                'gender' => 'male', 'date_of_birth' => '1972-10-03',
                'national_id' => '11010101022', 'phone' => '0933445566',
                'blood_type' => 'B+', 'marital_status' => 'married', 'city' => 'الخرطوم',
            ],
        ];

        $patients = collect();

        DB::transaction(function () use ($records, $branch, $sequences, $reception, $patients) {
            foreach ($records as $index => $record) {
                $patient = Patient::query()->create([
                    'mrn' => $sequences->next('patient_mrn', config('scarlet.mrn_prefix', 'SC'), 6),
                    'first_name_ar' => $record['first_name_ar'],
                    'last_name_ar' => $record['last_name_ar'],
                    'first_name_en' => $record['first_name_en'],
                    'last_name_en' => $record['last_name_en'],
                    'gender' => $record['gender'],
                    'date_of_birth' => $record['date_of_birth'],
                    'national_id' => $record['national_id'],
                    'phone' => $record['phone'],
                    'blood_type' => $record['blood_type'],
                    'marital_status' => $record['marital_status'],
                    'nationality' => 'سوداني',
                    'city' => $record['city'],
                    'status' => Patient::STATUS_ACTIVE,
                    'registration_branch_id' => $branch->id,
                    'created_by' => $reception->id,
                    'updated_by' => $reception->id,
                ]);

                DB::table('patients')->where('id', $patient->id)->update([
                    'created_at' => now()->subDays(30 - $index),
                    'updated_at' => now()->subDays(30 - $index),
                ]);

                $patient->refresh();

                if (isset($record['allergy'])) {
                    [$allergen, $reaction, $severity] = $record['allergy'];
                    $patient->allergies()->create([
                        'allergen' => $allergen,
                        'reaction' => $reaction,
                        'severity' => $severity,
                        'status' => 'active',
                        'recorded_by' => $reception->id,
                    ]);
                }

                if (isset($record['condition'])) {
                    [$type, $code, $nameAr, $nameEn, $diagnosedOn] = $record['condition'];
                    $patient->conditions()->create([
                        'type' => $type,
                        'code' => $code,
                        'name_ar' => $nameAr,
                        'name_en' => $nameEn,
                        'diagnosed_on' => $diagnosedOn,
                        'status' => 'active',
                        'recorded_by' => $reception->id,
                    ]);
                }

                $patients->push($patient);
            }
        });

        $at = fn (int $hour, int $minute = 0) => today()->setTime($hour, $minute);

        $first = $appointments->book([
            'patient_id' => $patients[0]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('IM'),
            'doctor_id' => $doctor->id,
            'scheduled_at' => $at(9)->toDateTimeString(),
            'type' => 'follow_up',
            'reason' => 'متابعة ضغط الدم',
        ], $reception);

        $second = $appointments->book([
            'patient_id' => $patients[1]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('PED'),
            'doctor_id' => $pediatrician->id,
            'scheduled_at' => $at(9, 30)->toDateTimeString(),
            'type' => 'new',
            'reason' => 'كحة متكررة',
        ], $reception);

        $third = $appointments->book([
            'patient_id' => $patients[2]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('IM'),
            'doctor_id' => $doctor->id,
            'scheduled_at' => $at(10)->toDateTimeString(),
            'type' => 'new',
            'reason' => 'ارتفاع سكر الدم',
        ], $reception);

        $appointments->book([
            'patient_id' => $patients[3]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('OBG'),
            'scheduled_at' => $at(10, 30)->toDateTimeString(),
            'type' => 'consultation',
            'reason' => 'استشارة نسائية',
        ], $reception);

        $fifth = $appointments->book([
            'patient_id' => $patients[4]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('IM'),
            'doctor_id' => $doctor->id,
            'scheduled_at' => $at(11)->toDateTimeString(),
            'type' => 'follow_up',
            'reason' => 'متابعة وظائف الكلى',
        ], $reception);

        $appointments->book([
            'patient_id' => $patients[5]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('PED'),
            'doctor_id' => $pediatrician->id,
            'scheduled_at' => $at(11, 30)->toDateTimeString(),
            'type' => 'new',
            'reason' => 'حساسية غذائية',
        ], $reception);

        $appointments->book([
            'patient_id' => $patients[6]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('DENT'),
            'scheduled_at' => $at(12)->toDateTimeString(),
            'type' => 'new',
            'reason' => 'ألم في الأسنان',
        ], $reception);

        $first = $appointments->checkIn($first, $reception);
        $firstTicket = $queues->start($first->queueTicket, $doctor);

        if ($firstTicket->encounter) {
            $encounter = $firstTicket->encounter;
            $encounters->addVitals($encounter, [
                'temperature_c' => 37.2,
                'heart_rate' => 88,
                'respiratory_rate' => 18,
                'systolic_bp' => 148,
                'diastolic_bp' => 92,
                'oxygen_saturation' => 97,
                'weight_kg' => 86.5,
                'height_cm' => 174,
                'pain_score' => 1,
            ], $nurse);
            $encounters->addDiagnosis($encounter, [
                'icd10_code' => 'I10',
                'name_ar' => 'ارتفاع ضغط الدم الأساسي',
                'name_en' => 'Essential hypertension',
                'type' => 'primary',
                'certainty' => 'confirmed',
            ], $doctor);
            $encounters->addNote($encounter, [
                'type' => 'progress',
                'content' => 'المريض يعاني من ارتفاع في ضغط الدم، لا توجد أعراض إنذارية. يوصى بالاستمرار على الدواء الحالي ومتابعة القياسات اليومية.',
            ], $doctor);
            $encounters->update($encounter, [
                'assessment' => 'ارتفاع ضغط الدم غير المنضبط نسبياً.',
                'plan' => 'تعديل جرعة الدواء، متابعة بعد أسبوعين، تحاليل وظائف الكلى.',
            ], $doctor);
        }

        $appointments->checkIn($second, $reception);
        $appointments->checkIn($third, $reception);

        $fifth = $appointments->checkIn($fifth, $reception);
        $fifthTicket = $queues->start($fifth->queueTicket, $doctor);

        if ($fifthTicket->encounter) {
            $encounter = $fifthTicket->encounter;
            $encounters->addVitals($encounter, [
                'temperature_c' => 36.8,
                'heart_rate' => 79,
                'respiratory_rate' => 16,
                'systolic_bp' => 132,
                'diastolic_bp' => 84,
                'oxygen_saturation' => 98,
                'weight_kg' => 70.2,
                'height_cm' => 160,
            ], $nurse);
            $encounters->addDiagnosis($encounter, [
                'icd10_code' => 'N18.3',
                'name_ar' => 'مرض الكلى المزمن - المرحلة الثالثة',
                'name_en' => 'Chronic kidney disease stage 3',
                'type' => 'primary',
                'certainty' => 'confirmed',
            ], $doctor);
            $encounters->addNote($encounter, [
                'type' => 'progress',
                'content' => 'الوظائف الكلوية مستقرة مقارنة بالزيارة السابقة. نوصي بالحمية منخفضة الصوديوم والمتابعة الدورية.',
                'sign' => true,
            ], $doctor);
            $encounters->update($encounter, [
                'assessment' => 'مرض كلوي مزمن مستقر.',
                'plan' => 'متابعة بعد ثلاثة أشهر مع تحاليل وظائف الكلى.',
                'disposition' => 'discharged',
            ], $doctor);
            $encounters->sign($encounter, $doctor);
        }

        $queues->issue($patients[7], $branch->id, $department('EMR'), null, $reception, priority: 'urgent');
        $queues->issue($patients[8], $branch->id, $department('EMR'), null, $reception);
        $queues->issue($patients[9], $branch->id, $department('IM'), $doctor->id, $reception);

        $past = $encounters->create([
            'patient_id' => $patients[0]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('IM'),
            'doctor_id' => $doctor->id,
            'type' => 'outpatient',
            'started_at' => now()->subDays(35)->setTime(10, 15),
            'chief_complaint' => 'صداع ودوخة',
        ], $doctor);

        $encounters->addVitals($past, [
            'systolic_bp' => 155,
            'diastolic_bp' => 95,
            'heart_rate' => 90,
            'temperature_c' => 36.9,
            'recorded_at' => now()->subDays(35)->setTime(10, 20),
        ], $nurse);
        $encounters->addDiagnosis($past, [
            'icd10_code' => 'I10',
            'name_ar' => 'ارتفاع ضغط الدم الأساسي',
            'name_en' => 'Essential hypertension',
            'type' => 'primary',
            'certainty' => 'confirmed',
        ], $doctor);
        $encounters->update($past, [
            'assessment' => 'ارتفاع ضغط الدم مع صداع.',
            'plan' => 'بدء العلاج الخافض للضغط ومتابعة أسبوعية.',
        ], $doctor);
        $encounters->sign($past, $doctor);

        $pastTwo = $encounters->create([
            'patient_id' => $patients[2]->id,
            'branch_id' => $branch->id,
            'department_id' => $department('IM'),
            'doctor_id' => $doctor->id,
            'type' => 'outpatient',
            'started_at' => now()->subDays(12)->setTime(9, 45),
            'chief_complaint' => 'عطش شديد وتبول متكرر',
        ], $doctor);

        $encounters->addVitals($pastTwo, [
            'systolic_bp' => 128,
            'diastolic_bp' => 82,
            'heart_rate' => 76,
            'temperature_c' => 36.7,
            'weight_kg' => 92.4,
            'recorded_at' => now()->subDays(12)->setTime(9, 50),
        ], $nurse);
        $encounters->addDiagnosis($pastTwo, [
            'icd10_code' => 'E11.9',
            'name_ar' => 'داء السكري من النوع الثاني بدون مضاعفات',
            'name_en' => 'Type 2 diabetes mellitus without complications',
            'type' => 'primary',
            'certainty' => 'confirmed',
        ], $doctor);
        $encounters->update($pastTwo, [
            'assessment' => 'سكر الدم غير منضبط.',
            'plan' => 'تعديل العلاج، حمية غذائية، متابعة بعد شهر.',
        ], $doctor);
        $encounters->sign($pastTwo, $doctor);

        $this->command?->info('Demo clinical data seeded: '.$patients->count().' patients with appointments, queues and encounters.');
    }
}
