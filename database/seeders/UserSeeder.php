<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->where('code', 'SCG')->firstOrFail();
        $clinic = Branch::query()->where('code', 'SCC')->first();
        $department = fn (string $code) => Department::query()
            ->where('branch_id', $branch->id)
            ->where('code', $code)
            ->value('id');

        $password = Hash::make('Scarlet@2026');

        $users = [
            [
                'name' => 'مدير النظام',
                'username' => 'admin',
                'email' => 'admin@scarlet.care',
                'phone' => '0900000001',
                'job_title' => 'مسؤول النظام',
                'roles' => ['super_admin'],
                'department_id' => null,
            ],
            [
                'name' => 'د. أحمد الطيب',
                'username' => 'doctor',
                'email' => 'doctor@scarlet.care',
                'phone' => '0900000002',
                'job_title' => 'استشاري باطنية',
                'roles' => ['doctor'],
                'department_id' => $department('IM'),
            ],
            [
                'name' => 'د. مريم حسن',
                'username' => 'dr.mariam',
                'email' => 'mariam@scarlet.care',
                'phone' => '0900000003',
                'job_title' => 'أخصائية أطفال',
                'roles' => ['doctor'],
                'department_id' => $department('PED'),
            ],
            [
                'name' => 'د. عثمان إبراهيم',
                'username' => 'dr.othman',
                'email' => 'othman@scarlet.care',
                'phone' => '0900000004',
                'job_title' => 'استشاري جراحة',
                'roles' => ['doctor'],
                'department_id' => $department('SUR'),
            ],
            [
                'name' => 'سارة محمد',
                'username' => 'reception',
                'email' => 'reception@scarlet.care',
                'phone' => '0900000005',
                'job_title' => 'موظفة استقبال',
                'roles' => ['receptionist'],
                'department_id' => $department('OPD'),
            ],
            [
                'name' => 'نور الهدى عثمان',
                'username' => 'nurse',
                'email' => 'nurse@scarlet.care',
                'phone' => '0900000006',
                'job_title' => 'ممرضة أولى',
                'roles' => ['nurse'],
                'department_id' => $department('EMR'),
            ],
            [
                'name' => 'ليلى منصور',
                'username' => 'labtech',
                'email' => 'labtech@scarlet.care',
                'phone' => '0900000011',
                'job_title' => 'فني مختبر',
                'roles' => ['laboratory_technician'],
                'department_id' => $department('LAB'),
            ],
            [
                'name' => 'خالد عمر',
                'username' => 'lab',
                'email' => 'lab@scarlet.care',
                'phone' => '0900000007',
                'job_title' => 'مشرف مختبر',
                'roles' => ['laboratory_supervisor'],
                'department_id' => $department('LAB'),
            ],
            [
                'name' => 'ريم علي',
                'username' => 'pharmacy',
                'email' => 'pharmacy@scarlet.care',
                'phone' => '0900000008',
                'job_title' => 'صيدلانية',
                'roles' => ['pharmacist'],
                'department_id' => $department('PHARM'),
            ],
            [
                'name' => 'عمر صالح',
                'username' => 'accountant',
                'email' => 'accountant@scarlet.care',
                'phone' => '0900000009',
                'job_title' => 'محاسب',
                'roles' => ['accountant'],
                'department_id' => null,
            ],
            [
                'name' => 'هدى عبد الله',
                'username' => 'insurance',
                'email' => 'insurance@scarlet.care',
                'phone' => '0900000010',
                'job_title' => 'مسؤولة تأمين',
                'roles' => ['insurance_officer'],
                'department_id' => null,
            ],
        ];

        foreach ($users as $data) {
            $user = User::query()->updateOrCreate(
                ['username' => $data['username']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => $password,
                    'locale' => 'ar',
                    'theme' => 'system',
                    'branch_id' => $branch->id,
                    'department_id' => $data['department_id'],
                    'job_title' => $data['job_title'],
                    'is_active' => true,
                ]
            );

            $roleIds = Role::query()->whereIn('name', $data['roles'])->pluck('id');
            $user->roles()->sync($roleIds);

            $branches = [$branch->id => ['is_default' => true]];

            if ($clinic) {
                $branches[$clinic->id] = ['is_default' => false];
            }

            $user->branches()->syncWithoutDetaching($branches);
        }

        $this->command?->info('Users seeded: '.count($users).' demo accounts (password: Scarlet@2026).');
    }
}
