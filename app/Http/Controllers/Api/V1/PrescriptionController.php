<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Models\Branch;
use App\Domains\Pharmacy\Models\Dispense;
use App\Domains\Pharmacy\Models\Medication;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Pharmacy\Models\PrescriptionItem;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends Controller
{
    public function __construct(private readonly SequenceService $sequences) {}

    public function index(Request $request): JsonResponse
    {
        $query = Prescription::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en', 'doctor:id,name', 'branch:id,name_ar,name_en', 'items.medication']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($branchId = $request->integer('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        $prescriptions = $query->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($prescriptions, $prescriptions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medication_id' => ['required', 'integer', 'exists:medications,id'],
            'items.*.dosage' => ['nullable', 'string', 'max:60'],
            'items.*.frequency' => ['nullable', 'string', 'max:60'],
            'items.*.duration' => ['nullable', 'string', 'max:60'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.instructions' => ['nullable', 'string'],
        ]);

        $prescription = DB::transaction(function () use ($data, $request) {
            $prescription = Prescription::create([
                'prescription_number' => $this->sequences->next('prescription', 'PRX', 6),
                'patient_id' => $data['patient_id'],
                'encounter_id' => $data['encounter_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'branch_id' => $data['branch_id'],
                'doctor_id' => $data['doctor_id'] ?? $request->user()->id,
                'status' => Prescription::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'prescribed_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $prescription->items()->create($item);
            }

            return $prescription;
        });

        $prescription->load(['patient', 'doctor', 'branch', 'items.medication']);

        return ApiResponse::created($prescription, 'Prescription created.');
    }

    public function show(Prescription $prescription): JsonResponse
    {
        $prescription->load(['patient', 'doctor', 'branch', 'encounter', 'items.medication', 'dispenses.medication', 'dispenses.dispenser']);

        return ApiResponse::success($prescription);
    }

    public function dispense(Request $request, Prescription $prescription): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.prescription_item_id' => ['required', 'integer', 'exists:prescription_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($prescription->status === Prescription::STATUS_CANCELLED) {
            return ApiResponse::error('Cannot dispense a cancelled prescription.', 422);
        }

        DB::transaction(function () use ($data, $prescription, $request) {
            foreach ($data['items'] as $row) {
                $item = PrescriptionItem::query()->where('prescription_id', $prescription->id)->findOrFail($row['prescription_item_id']);
                $medication = Medication::query()->lockForUpdate()->findOrFail($item->medication_id);

                if ($medication->stock_quantity < $row['quantity']) {
                    abort(422, 'Insufficient stock for '.$medication->name_ar.'.');
                }

                $medication->decrement('stock_quantity', $row['quantity']);

                Dispense::create([
                    'prescription_id' => $prescription->id,
                    'prescription_item_id' => $item->id,
                    'medication_id' => $medication->id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $medication->unit_price,
                    'total_price' => $medication->unit_price * $row['quantity'],
                    'dispensed_by' => $request->user()->id,
                    'dispensed_at' => now(),
                ]);
            }

            $prescription->update([
                'status' => Prescription::STATUS_DISPENSED,
                'dispensed_at' => now(),
                'dispensed_by' => $request->user()->id,
            ]);
        });

        $prescription->refresh()->load(['patient', 'items.medication', 'dispenses.medication']);

        return ApiResponse::success($prescription, 'Prescription dispensed.');
    }

    public function cancel(Prescription $prescription): JsonResponse
    {
        if ($prescription->status === Prescription::STATUS_DISPENSED) {
            return ApiResponse::error('Cannot cancel a dispensed prescription.', 422);
        }

        $prescription->update(['status' => Prescription::STATUS_CANCELLED]);

        return ApiResponse::success($prescription, 'Prescription cancelled.');
    }
}
