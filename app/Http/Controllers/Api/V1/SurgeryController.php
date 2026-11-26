<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inpatient\Models\Surgery;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurgeryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $surgeries = Surgery::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar', 'doctor:id,name'])
            ->orderByDesc('scheduled_at')
            ->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($surgeries, $surgeries);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'procedure_name' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $surgery = Surgery::create([
            'patient_id' => $data['patient_id'],
            'doctor_id' => $data['doctor_id'] ?? $request->user()->id,
            'procedure_name' => $data['procedure_name'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => 'scheduled',
            'notes' => $data['notes'] ?? null,
        ]);

        return ApiResponse::created($surgery->load(['patient', 'doctor']), 'Surgery scheduled.');
    }

    public function update(Request $request, Surgery $surgery): JsonResponse
    {
        $data = $request->validate([
            'procedure_name' => ['sometimes', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);
        $surgery->update($data);
        return ApiResponse::success($surgery, 'Surgery updated.');
    }
}
