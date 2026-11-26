<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Models\User;
use App\Domains\Laboratory\Models\LabOrder;
use App\Domains\Laboratory\Models\LabOrderItem;
use App\Domains\Laboratory\Models\LabTest;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabOrderController extends Controller
{
    public function __construct(private readonly SequenceService $sequences) {}

    private function hasFullLabAccess(User $user): bool
    {
        return $user->hasRole(['super_admin', 'laboratory_supervisor']);
    }

    private function assertCanCollect(User $user): void
    {
        abort_if(
            $user->hasRole('laboratory_technician') && ! $this->hasFullLabAccess($user),
            403,
            'Sample collection is performed by the sample collector.',
        );
    }

    private function assertCanEnter(User $user): void
    {
        abort_if(
            $user->hasRole('sample_collector') && ! $this->hasFullLabAccess($user),
            403,
            'Result entry is performed by the laboratory technician.',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $query = LabOrder::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en', 'doctor:id,name', 'branch:id,name_ar,name_en', 'items.test']);

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
            'notes' => ['nullable', 'string'],
            'test_ids' => ['required', 'array', 'min:1'],
            'test_ids.*' => ['integer', 'exists:lab_tests,id'],
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $order = LabOrder::create([
                'order_number' => $this->sequences->next('lab_order', 'LAB', 6),
                'patient_id' => $data['patient_id'],
                'encounter_id' => $data['encounter_id'] ?? null,
                'branch_id' => $data['branch_id'],
                'doctor_id' => $data['doctor_id'] ?? $request->user()->id,
                'status' => LabOrder::STATUS_ORDERED,
                'ordered_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['test_ids'] as $testId) {
                $test = LabTest::findOrFail($testId);
                $order->items()->create([
                    'lab_test_id' => $test->id,
                    'status' => LabOrderItem::STATUS_PENDING,
                    'result_unit' => $test->unit,
                ]);
            }

            return $order;
        });

        $order->load(['patient', 'items.test']);

        return ApiResponse::created($order, 'Lab order created.');
    }

    public function show(LabOrder $order): JsonResponse
    {
        $order->load(['patient', 'doctor', 'branch', 'encounter', 'items.test', 'items.enteredBy', 'items.approvedBy']);

        return ApiResponse::success($order);
    }

    public function collectSample(Request $request, LabOrderItem $item): JsonResponse
    {
        $this->assertCanCollect($request->user());
        abort_if($item->sample_collected_at !== null, 422, 'Sample has already been collected.');

        $item->update([
            'sample_collected_at' => now(),
            'sample_collected_by' => $request->user()->id,
            'status' => LabOrderItem::STATUS_PENDING,
        ]);

        return ApiResponse::success($item->fresh(['test', 'order']), 'Sample collected.');
    }

    public function enterResult(Request $request, LabOrderItem $item): JsonResponse
    {
        $this->assertCanEnter($request->user());
        abort_if($item->sample_collected_at === null, 422, 'Sample must be collected before entering a result.');
        abort_if($item->status === LabOrderItem::STATUS_APPROVED, 422, 'Approved results cannot be changed.');

        $data = $request->validate([
            'result_value' => ['required', 'string'],
            'result_unit' => ['nullable', 'string', 'max:30'],
            'flag' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $item->update([
            'status' => LabOrderItem::STATUS_ENTERED,
            'result_value' => $data['result_value'],
            'result_unit' => $data['result_unit'] ?? $item->result_unit,
            'flag' => $data['flag'] ?? null,
            'notes' => $data['notes'] ?? null,
            'entered_by' => $request->user()->id,
            'entered_at' => now(),
        ]);

        return ApiResponse::success($item->fresh(['test', 'enteredBy']), 'Result entered.');
    }

    public function saveValues(Request $request, LabOrderItem $item): JsonResponse
    {
        $this->assertCanEnter($request->user());
        abort_if($item->sample_collected_at === null, 422, 'Sample must be collected before entering a result.');
        abort_if($item->status === LabOrderItem::STATUS_APPROVED, 422, 'Approved results cannot be changed.');

        $data = $request->validate([
            'values' => ['required', 'array'],
            'values.*.parameter_id' => ['required', 'integer', 'exists:lab_test_parameters,id'],
            'values.*.value' => ['nullable', 'string', 'max:255'],
            'values.*.flag' => ['nullable', 'string', 'max:20'],
        ]);

        $validParameterIds = $item->test->parameters()->pluck('id')->all();

        foreach ($data['values'] as $value) {
            abort_unless(in_array($value['parameter_id'], $validParameterIds, true), 422, 'Parameter does not belong to this test.');

            $item->values()->updateOrCreate(
                ['lab_test_parameter_id' => $value['parameter_id']],
                [
                    'value' => $value['value'] ?? null,
                    'flag' => $value['flag'] ?? null,
                ],
            );
        }

        $summary = $item->values()->with('parameter')
            ->get()
            ->map(fn ($v) => $v->parameter->name_ar.': '.$v->value.($v->flag ? ' ['.$v->flag.']' : ''))
            ->join('؛ ');

        $item->update([
            'status' => LabOrderItem::STATUS_ENTERED,
            'result_value' => $summary,
            'entered_by' => $request->user()->id,
            'entered_at' => now(),
        ]);

        return ApiResponse::success($item->fresh(['test.parameters', 'values.parameter']), 'Result values saved.');
    }

    public function approveResult(Request $request, LabOrderItem $item): JsonResponse
    {
        abort_if($item->status !== LabOrderItem::STATUS_ENTERED, 422, 'Only entered results can be approved.');

        $item->update([
            'status' => LabOrderItem::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $order = $item->order;
        if ($order->items()->where('status', '!=', LabOrderItem::STATUS_APPROVED)->doesntExist()) {
            $order->update([
                'status' => LabOrder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return ApiResponse::success($item->fresh(['test', 'approvedBy']), 'Result approved.');
    }
}
