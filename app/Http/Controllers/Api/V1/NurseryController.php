<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inpatient\Models\NurseryRecord;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NurseryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $records = NurseryRecord::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar', 'bed:id,code'])
            ->orderByDesc('admitted_at')
            ->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($records, $records);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'bed_id' => ['nullable', 'integer', 'exists:beds,id'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = NurseryRecord::create([
            'patient_id' => $data['patient_id'],
            'bed_id' => $data['bed_id'] ?? null,
            'admitted_at' => now(),
            'weight_kg' => $data['weight_kg'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($record->bed_id) {
            \App\Domains\Inpatient\Models\Bed::query()->where('id', $record->bed_id)->update(['status' => 'occupied']);
        }

        return ApiResponse::created($record->load(['patient', 'bed']), 'Nursery admission created.');
    }

    public function discharge(NurseryRecord $record): JsonResponse
    {
        $record->update(['discharged_at' => now()]);
        if ($record->bed_id) {
            \App\Domains\Inpatient\Models\Bed::query()->where('id', $record->bed_id)->update(['status' => 'available']);
        }
        return ApiResponse::success($record, 'Nursery discharge recorded.');
    }
}
