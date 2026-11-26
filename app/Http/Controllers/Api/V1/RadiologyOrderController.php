<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Radiology\Models\RadiologyOrder;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RadiologyOrderController extends Controller
{
    public function __construct(private readonly SequenceService $sequences) {}

    public function index(Request $request): JsonResponse
    {
        $query = RadiologyOrder::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en', 'doctor:id,name', 'branch:id,name_ar,name_en', 'sourceDevice:id,code,name,modality']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        $orders = $query->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($orders, $orders);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'modality' => ['nullable', 'string', 'max:40'],
            'study_description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $order = RadiologyOrder::create([
            'order_number' => $this->sequences->next('radiology_order', 'RAD', 6),
            'patient_id' => $data['patient_id'],
            'encounter_id' => $data['encounter_id'] ?? null,
            'branch_id' => $data['branch_id'],
            'doctor_id' => $data['doctor_id'] ?? $request->user()->id,
            'modality' => $data['modality'] ?? null,
            'study_description' => $data['study_description'],
            'status' => RadiologyOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);

        return ApiResponse::created($order->load(['patient', 'doctor', 'branch']), 'Radiology order created.');
    }

    public function show(RadiologyOrder $order): JsonResponse
    {
        return ApiResponse::success($order->load(['patient', 'doctor', 'branch', 'encounter', 'reporter', 'study', 'sourceDevice', 'sourceMessage']));
    }

    public function report(Request $request, RadiologyOrder $order): JsonResponse
    {
        $data = $request->validate([
            'report' => ['required', 'string'],
        ]);

        $order->update([
            'report' => $data['report'],
            'status' => RadiologyOrder::STATUS_COMPLETED,
            'completed_at' => now(),
            'reported_by' => $request->user()->id,
        ]);

        return ApiResponse::success($order->fresh(['reporter']), 'Radiology report saved.');
    }

    public function confirmResult(Request $request, RadiologyOrder $order): JsonResponse
    {
        abort_if($order->status === RadiologyOrder::STATUS_CANCELLED, 422, 'Cancelled orders cannot be confirmed.');
        abort_if($order->status === RadiologyOrder::STATUS_COMPLETED, 422, 'Report is already completed.');

        $data = $request->validate([
            'report' => ['nullable', 'string'],
        ]);

        $order->update([
            'report' => $data['report'] ?? $order->report,
            'status' => RadiologyOrder::STATUS_COMPLETED,
            'completed_at' => now(),
            'reported_by' => $request->user()->id,
        ]);

        return ApiResponse::success($order->fresh(['reporter', 'study', 'sourceDevice']), 'Device result confirmed and signed.');
    }

    public function cancel(RadiologyOrder $order): JsonResponse
    {
        $order->update(['status' => RadiologyOrder::STATUS_CANCELLED]);

        return ApiResponse::success($order, 'Radiology order cancelled.');
    }
}
