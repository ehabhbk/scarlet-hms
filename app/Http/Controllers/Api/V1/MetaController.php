<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Settings\Models\Setting;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\SettingResource;
use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    /**
     * Everything the authenticated shell needs after login.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions', 'branch', 'department', 'branches']);

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get();

        $doctors = User::query()
            ->with(['branch:id,name_ar,name_en', 'department:id,name_ar,name_en'])
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['doctor', 'medical_director', 'nurse']))
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'branch_id', 'department_id', 'job_title']);

        $roles = Role::query()->withCount('permissions')->orderBy('name_en')->get();

        $settings = Setting::query()
            ->where('is_public', true)
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        return ApiResponse::success([
            'user' => new UserResource($user),
            'branches' => BranchResource::collection($branches),
            'departments' => DepartmentResource::collection($departments),
            'doctors' => $doctors->map(fn (User $doctor) => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'job_title' => $doctor->job_title,
                'branch_id' => $doctor->branch_id,
                'department_id' => $doctor->department_id,
                'branch' => $doctor->branch?->only(['id', 'name_ar', 'name_en']),
                'department' => $doctor->department?->only(['id', 'name_ar', 'name_en']),
            ])->values(),
            'roles' => RoleResource::collection($roles),
            'permissions' => PermissionResource::collection(
                Permission::query()->orderBy('module')->orderBy('name')->get()
            ),
            'settings' => SettingResource::collection($settings),
            'options' => $this->options(),
        ]);
    }

    /**
     * Public shell metadata (branding, branches, departments).
     */
    public function publicMeta(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        $branchId = $request->integer('branch_id') ?: $branches->first()?->id;

        $departments = Department::query()
            ->where('is_active', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get();

        return ApiResponse::success([
            'brand' => [
                'name' => config('scarlet.name'),
                'name_ar' => config('scarlet.name_ar'),
                'legal_name' => config('scarlet.legal_name'),
                'tagline_ar' => config('scarlet.tagline_ar'),
                'tagline_en' => config('scarlet.tagline_en'),
                'palette' => config('scarlet.brand'),
            ],
            'branches' => BranchResource::collection($branches),
            'departments' => DepartmentResource::collection($departments),
            'settings' => SettingResource::collection(
                Setting::query()->where('is_public', true)->get()
            ),
            'options' => $this->options(),
        ]);
    }

    /**
     * Shared enum values and their bilingual labels.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'genders' => [
                ['value' => 'male', 'ar' => 'ذكر', 'en' => 'Male'],
                ['value' => 'female', 'ar' => 'أنثى', 'en' => 'Female'],
                ['value' => 'other', 'ar' => 'آخر', 'en' => 'Other'],
            ],
            'marital_statuses' => [
                ['value' => 'single', 'ar' => 'أعزب', 'en' => 'Single'],
                ['value' => 'married', 'ar' => 'متزوج', 'en' => 'Married'],
                ['value' => 'divorced', 'ar' => 'مطلق', 'en' => 'Divorced'],
                ['value' => 'widowed', 'ar' => 'أرمل', 'en' => 'Widowed'],
                ['value' => 'unknown', 'ar' => 'غير معروف', 'en' => 'Unknown'],
            ],
            'blood_types' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'appointment_statuses' => [
                ['value' => 'booked', 'ar' => 'محجوز', 'en' => 'Booked'],
                ['value' => 'checked_in', 'ar' => 'تم الوصول', 'en' => 'Checked in'],
                ['value' => 'in_progress', 'ar' => 'قيد الزيارة', 'en' => 'In progress'],
                ['value' => 'completed', 'ar' => 'مكتمل', 'en' => 'Completed'],
                ['value' => 'cancelled', 'ar' => 'ملغي', 'en' => 'Cancelled'],
                ['value' => 'no_show', 'ar' => 'لم يحضر', 'en' => 'No show'],
            ],
            'appointment_types' => [
                ['value' => 'new', 'ar' => 'زيارة جديدة', 'en' => 'New'],
                ['value' => 'follow_up', 'ar' => 'متابعة', 'en' => 'Follow-up'],
                ['value' => 'walk_in', 'ar' => 'بدون موعد', 'en' => 'Walk-in'],
                ['value' => 'consultation', 'ar' => 'استشارة', 'en' => 'Consultation'],
            ],
            'queue_statuses' => [
                ['value' => 'waiting', 'ar' => 'بالانتظار', 'en' => 'Waiting'],
                ['value' => 'called', 'ar' => 'تم النداء', 'en' => 'Called'],
                ['value' => 'in_progress', 'ar' => 'قيد الخدمة', 'en' => 'In progress'],
                ['value' => 'completed', 'ar' => 'مكتمل', 'en' => 'Completed'],
                ['value' => 'skipped', 'ar' => 'تم التخطي', 'en' => 'Skipped'],
                ['value' => 'no_show', 'ar' => 'لم يحضر', 'en' => 'No show'],
            ],
            'queue_priorities' => [
                ['value' => 'normal', 'ar' => 'عادي', 'en' => 'Normal'],
                ['value' => 'urgent', 'ar' => 'عاجل', 'en' => 'Urgent'],
                ['value' => 'emergency', 'ar' => 'طارئ', 'en' => 'Emergency'],
            ],
            'encounter_types' => [
                ['value' => 'outpatient', 'ar' => 'عيادات خارجية', 'en' => 'Outpatient'],
                ['value' => 'emergency', 'ar' => 'طوارئ', 'en' => 'Emergency'],
                ['value' => 'inpatient', 'ar' => 'تنويم', 'en' => 'Inpatient'],
                ['value' => 'surgery', 'ar' => 'جراحة', 'en' => 'Surgery'],
                ['value' => 'icu', 'ar' => 'عناية مركزة', 'en' => 'ICU'],
                ['value' => 'dental', 'ar' => 'أسنان', 'en' => 'Dental'],
            ],
            'encounter_statuses' => [
                ['value' => 'open', 'ar' => 'مفتوحة', 'en' => 'Open'],
                ['value' => 'in_progress', 'ar' => 'قيد التوقيع', 'en' => 'In progress'],
                ['value' => 'completed', 'ar' => 'مكتملة', 'en' => 'Completed'],
                ['value' => 'cancelled', 'ar' => 'ملغاة', 'en' => 'Cancelled'],
            ],
            'diagnosis_certainties' => [
                ['value' => 'suspected', 'ar' => 'مشتبه', 'en' => 'Suspected'],
                ['value' => 'confirmed', 'ar' => 'مؤكد', 'en' => 'Confirmed'],
                ['value' => 'rule_out', 'ar' => 'مستبعد', 'en' => 'Rule out'],
            ],
            'allergy_severities' => [
                ['value' => 'mild', 'ar' => 'خفيفة', 'en' => 'Mild'],
                ['value' => 'moderate', 'ar' => 'متوسطة', 'en' => 'Moderate'],
                ['value' => 'severe', 'ar' => 'شديدة', 'en' => 'Severe'],
                ['value' => 'life_threatening', 'ar' => 'مهددة للحياة', 'en' => 'Life threatening'],
            ],
            'patient_statuses' => [
                ['value' => 'active', 'ar' => 'نشط', 'en' => 'Active'],
                ['value' => 'inactive', 'ar' => 'غير نشط', 'en' => 'Inactive'],
                ['value' => 'deceased', 'ar' => 'متوفى', 'en' => 'Deceased'],
            ],
        ];
    }
}
