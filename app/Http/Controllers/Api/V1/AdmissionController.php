<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inpatient\Models\Admission;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admissions = Admission::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en', 'bed:id,code', 'doctor:id,name'])
            ->orderByDesc('admitted_at')
            ->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($admissions, $admissions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'bed_id' => ['nullable', 'integer', 'exists:beds,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'diagnosis' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $admission = Admission::create([
            'patient_id' => $data['patient_id'],
            'bed_id' => $data['bed_id'] ?? null,
            'doctor_id' => $data['doctor_id'] ?? $request->user()->id,
            'status' => 'admitted',
            'admitted_at' => now(),
            'diagnosis' => $data['diagnosis'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($admission->bed_id) {
            \App\Domains\Inpatient\Models\Bed::query()->where('id', $admission->bed_id)->update(['status' => 'occupied']);
        }

        return ApiResponse::created($admission->load(['patient', 'bed', 'doctor']), 'Admission created.');
    }

    public function discharge(Admission $admission): JsonResponse
    {
        $admission->update([
            'status' => 'discharged',
            'discharged_at' => now(),
        ]);

        if ($admission->bed_id) {
            \App\Domains\Inpatient\Models\Bed::query()->where('id', $admission->bed_id)->update(['status' => 'available']);
        }

        return ApiResponse::success($admission, 'Patient discharged.');
    }
}
