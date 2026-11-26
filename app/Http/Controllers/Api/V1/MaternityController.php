<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inpatient\Models\MaternityRecord;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaternityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $records = MaternityRecord::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar', 'doctor:id,name'])
            ->orderByDesc('created_at')
            ->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($records, $records);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'edd' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = MaternityRecord::create([
            'patient_id' => $data['patient_id'],
            'doctor_id' => $data['doctor_id'] ?? $request->user()->id,
            'edd' => $data['edd'] ?? null,
            'status' => $data['status'] ?? 'pregnant',
            'notes' => $data['notes'] ?? null,
        ]);

        return ApiResponse::created($record->load(['patient', 'doctor']), 'Maternity record created.');
    }

    public function update(Request $request, MaternityRecord $record): JsonResponse
    {
        $data = $request->validate([
            'edd' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);
        $record->update($data);
        return ApiResponse::success($record, 'Maternity record updated.');
    }
}
