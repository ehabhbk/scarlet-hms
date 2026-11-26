<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Insurance\Models\PatientInsurance;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientInsuranceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PatientInsurance::query()->with(['patient:id,mrn,first_name_ar,last_name_ar', 'provider']);

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        $policies = $query->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($policies, $policies);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'insurance_provider_id' => ['required', 'integer', 'exists:insurance_providers,id'],
            'policy_number' => ['required', 'string', 'max:60'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $policy = PatientInsurance::create($data);

        return ApiResponse::created($policy->load('provider'), 'Insurance policy created.');
    }

    public function show(PatientInsurance $policy): JsonResponse
    {
        return ApiResponse::success($policy->load(['patient', 'provider']));
    }

    public function update(Request $request, PatientInsurance $policy): JsonResponse
    {
        $data = $request->validate([
            'insurance_provider_id' => ['sometimes', 'integer', 'exists:insurance_providers,id'],
            'policy_number' => ['sometimes', 'string', 'max:60'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $policy->update($data);

        return ApiResponse::success($policy->fresh(['provider']), 'Insurance policy updated.');
    }

    public function destroy(PatientInsurance $policy): JsonResponse
    {
        $policy->delete();

        return ApiResponse::success(null, 'Insurance policy deleted.');
    }
}
