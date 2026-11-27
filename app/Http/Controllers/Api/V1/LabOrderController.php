<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Identity\Models\User;
use App\Domains\Insurance\Models\PatientInsurance;
use App\Domains\Laboratory\Models\LabOrder;
use App\Domains\Laboratory\Models\LabOrderItem;
use App\Domains\Laboratory\Models\LabTest;
use App\Domains\Settings\Models\Setting;
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

    /**
     * Worklist for the result-entry workspace.
     *
     * Returns every order with collected samples, including approved and
     * printed ones, so the UI can colour each ring by its true result state.
     * The UI renders one ring per returned order, so no arbitrary limit is
     * applied here.
     */
    /**
     * One status for the whole sample ring.
     * printed > approved > entered > empty.
     */
    private function worklistStatus(LabOrder $order): string
    {
        $items = $order->items;
        if ($items->isEmpty()) {
            return 'empty';
        }

        if ($items->every(fn (LabOrderItem $item) => $item->printed_at !== null)) {
            return 'printed';
        }

        if ($items->every(fn (LabOrderItem $item) => $item->status === LabOrderItem::STATUS_APPROVED)) {
            return 'approved';
        }

        if ($items->contains(fn (LabOrderItem $item) => $item->status === LabOrderItem::STATUS_ENTERED)) {
            return 'entered';
        }

        return 'empty';
    }

    public function worklist(): JsonResponse
    {
        $orders = LabOrder::query()
            ->with([
                'patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en,national_id,gender,date_of_birth,phone,address,status,registration_branch_id,created_at',
                'patient.registrationBranch:id,code,name_ar,name_en',
                'doctor:id,name',
                'branch:id,name_ar,name_en',
                'items' => fn ($q) => $q->whereNotNull('sample_collected_at')
                    ->with([
                        'test.parameters',
                        'test.devices:id,code,name,protocol,host,port,status',
                        'values.parameter',
                        'sourceDevice:id,code,name,protocol,host,port',
                        'enteredByUser:id,name',
                        'approvedByUser:id,name',
                        'printedByUser:id,name',
                        'sampleCollectorUser:id,name',
                        'sourceMessage:id,lab_device_id,status,parsed_summary,received_at',
                    ]),
            ])
            ->whereHas('items', fn ($q) => $q->whereNotNull('sample_collected_at'))
            ->orderBy('id')
            ->get();

        // Expose the device assigned to each test so the UI never asks the user
        // to choose one; the mapping comes from the lab device settings.
        $orders->each(function (LabOrder $order): void {
            $order->items->each(function (LabOrderItem $item): void {
                $item->setRelation(
                    'assigned_device',
                    $item->test->devices->firstWhere('status', 'active'),
                );
            });
        });

        $patientIds = $orders->pluck('patient_id')->unique()->all();

        $invoices = Invoice::query()
            ->whereIn('patient_id', $patientIds)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->get()
            ->groupBy('patient_id');

        $policies = PatientInsurance::query()
            ->with('provider:id,code,name,coverage_percent')
            ->whereIn('patient_id', $patientIds)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->get()
            ->groupBy('patient_id');

        $rows = $orders->map(function (LabOrder $order) use ($invoices, $policies): array {
            $invoice = $invoices->get($order->patient_id)?->first();
            $policy = $policies->get($order->patient_id)?->first();

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'ordered_at' => $order->ordered_at,
                'notes' => $order->notes,
                'patient' => $order->patient,
                'registration_branch' => $order->patient?->registrationBranch,
                'doctor' => $order->doctor,
                'branch' => $order->branch,
                'tests_count' => $order->items->count(),
                'result_status' => $this->worklistStatus($order),
                'all_approved' => $order->items->isNotEmpty()
                    && $order->items->every(fn (LabOrderItem $item) => $item->status === LabOrderItem::STATUS_APPROVED),
                'payment' => $invoice ? [
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'total' => $invoice->total,
                    'paid' => $invoice->paid,
                    'balance' => $invoice->balance,
                    'currency' => $invoice->currency,
                ] : null,
                'insurance' => $policy ? [
                    'provider' => $policy->provider,
                    'policy_number' => $policy->policy_number,
                    'valid_to' => $policy->valid_to,
                    'status' => $policy->status,
                ] : null,
                'items' => $order->items,
            ];
        });

        return ApiResponse::success($rows, 'Lab worklist prepared.');
    }

    /**
     * Collection worklist: one ring per real, non-cancelled lab order.
     *
     * Unlike the result worklist this includes orders whose samples have not
     * been collected yet, so the collector sees the full dynamic queue.
     */
    public function collectionWorklist(): JsonResponse
    {
        $orders = LabOrder::query()
            ->with([
                'patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en,national_id,gender,date_of_birth,phone,address,status,registration_branch_id,created_at',
                'patient.registrationBranch:id,code,name_ar,name_en',
                'doctor:id,name',
                'branch:id,name_ar,name_en',
                'items' => fn ($q) => $q->with([
                    'test:id,code,name_ar,name_en,price,unit,is_active',
                    'sampleCollectorUser:id,name',
                ]),
            ])
            ->where('status', '!=', LabOrder::STATUS_CANCELLED)
            ->orderBy('id')
            ->get();

        $patientIds = $orders->pluck('patient_id')->unique()->all();

        $invoices = Invoice::query()
            ->whereIn('patient_id', $patientIds)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->get()
            ->groupBy('patient_id');

        $policies = PatientInsurance::query()
            ->with('provider:id,code,name,coverage_percent')
            ->whereIn('patient_id', $patientIds)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->get()
            ->groupBy('patient_id');

        $rows = $orders->map(function (LabOrder $order) use ($invoices, $policies): array {
            $invoice = $invoices->get($order->patient_id)?->first();
            $policy = $policies->get($order->patient_id)?->first();
            $collected = $order->items->filter(fn (LabOrderItem $item) => $item->sample_collected_at !== null);
            $pending = $order->items->count() - $collected->count();

            $collectionStatus = match (true) {
                $order->items->isEmpty() || $pending === $order->items->count() => 'pending',
                $pending === 0 => 'collected',
                default => 'partial',
            };

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'ordered_at' => $order->ordered_at,
                'notes' => $order->notes,
                'patient' => $order->patient,
                'registration_branch' => $order->patient?->registrationBranch,
                'doctor' => $order->doctor,
                'branch' => $order->branch,
                'tests_count' => $order->items->count(),
                'collected_count' => $collected->count(),
                'pending_count' => $pending,
                'collection_status' => $collectionStatus,
                'first_collected_at' => $collected->min('sample_collected_at'),
                'payment' => $invoice ? [
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'total' => $invoice->total,
                    'paid' => $invoice->paid,
                    'balance' => $invoice->balance,
                    'currency' => $invoice->currency,
                ] : null,
                'insurance' => $policy ? [
                    'provider' => $policy->provider,
                    'policy_number' => $policy->policy_number,
                    'valid_to' => $policy->valid_to,
                    'status' => $policy->status,
                ] : null,
                'items' => $order->items,
            ];
        });

        return ApiResponse::success($rows, 'Sample collection worklist prepared.');
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
                'order_number' => (string) $this->sequences->nextNumber('lab_order', '', 1),
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

        $collectedAt = now();

        DB::transaction(function () use ($item, $request, $collectedAt): void {
            $item->update([
                'sample_collected_at' => $collectedAt,
                'sample_collected_by' => $request->user()->id,
                'status' => LabOrderItem::STATUS_PENDING,
            ]);

            $this->markOrderInProgress($item->order);
        });

        return ApiResponse::success($item->fresh(['test', 'order']), 'Sample collected.');
    }

    public function collectOrderSamples(Request $request, LabOrder $order): JsonResponse
    {
        $this->assertCanCollect($request->user());
        abort_if($order->status === LabOrder::STATUS_CANCELLED, 422, 'Cancelled orders cannot be collected.');

        $items = $order->items()->whereNull('sample_collected_at')->get();
        abort_if($items->isEmpty(), 422, 'All samples for this order have already been collected.');

        $collectedAt = now();
        $userId = $request->user()->id;

        DB::transaction(function () use ($items, $order, $collectedAt, $userId): void {
            foreach ($items as $item) {
                $item->update([
                    'sample_collected_at' => $collectedAt,
                    'sample_collected_by' => $userId,
                    'status' => LabOrderItem::STATUS_PENDING,
                ]);
            }

            $this->markOrderInProgress($order);
        });

        return ApiResponse::success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'collected_count' => $items->count(),
        ], 'Samples collected.');
    }

    private function markOrderInProgress(LabOrder $order): void
    {
        if ($order->status === LabOrder::STATUS_ORDERED) {
            $order->update(['status' => LabOrder::STATUS_IN_PROGRESS]);
        }
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

        if (! (bool) Setting::get('lab_allow_empty_results', false)) {
            abort_unless($this->itemHasResults($item), 422, 'لا يمكن اعتماد نتيجة تحتوي حقولاً فارغة.');
        }

        $item->update([
            'status' => LabOrderItem::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'printed_at' => null,
            'printed_by' => null,
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

    /**
     * A supervisor can withdraw approval so the technician can correct a
     * result. Withdrawal also clears the print marker.
     */
    public function unapproveResult(Request $request, LabOrderItem $item): JsonResponse
    {
        abort_if($item->status !== LabOrderItem::STATUS_APPROVED, 422, 'Only approved results can be unapproved.');

        $item->update([
            'status' => LabOrderItem::STATUS_ENTERED,
            'approved_by' => null,
            'approved_at' => null,
            'printed_at' => null,
            'printed_by' => null,
        ]);

        $order = $item->order;
        if ($order->status === LabOrder::STATUS_COMPLETED) {
            $order->update(['status' => LabOrder::STATUS_IN_PROGRESS, 'completed_at' => null]);
        }

        return ApiResponse::success($item->fresh(['test', 'values.parameter']), 'Approval withdrawn; result can be edited.');
    }

    /**
     * Mark the whole sample as printed. Only fully approved orders can be
     * printed; the actual report is rendered by the client from the full order.
     */
    public function printOrder(Request $request, LabOrder $order): JsonResponse
    {
        $order->loadMissing('items');

        abort_if($order->items->isEmpty(), 422, 'لا توجد فحوصات في هذه العينة.');
        abort_unless(
            $order->items->every(fn (LabOrderItem $item) => $item->status === LabOrderItem::STATUS_APPROVED),
            422,
            'لا يمكن طباعة النتيجة إلا بعد إدخال جميع الفحوصات واعتمادها.',
        );

        $order->items->each(fn (LabOrderItem $item) => $item->update([
            'printed_at' => now(),
            'printed_by' => $request->user()->id,
        ]));

        return ApiResponse::success(
            $order->fresh(['patient', 'doctor', 'branch', 'items.test.parameters', 'items.values.parameter', 'items.approvedByUser', 'items.enteredByUser']),
            'تم تسجيل طباعة النتيجة كاملة.',
        );
    }

    private function itemHasResults(LabOrderItem $item): bool
    {
        $item->loadMissing('test.parameters', 'values');

        $parameters = $item->test->parameters;

        if ($parameters->isEmpty()) {
            return filled(trim((string) $item->result_value));
        }

        $values = $item->values->keyBy('lab_test_parameter_id');

        return $parameters->every(function ($parameter) use ($values): bool {
            $value = $values->get($parameter->id);

            return $value !== null && filled(trim((string) $value->value));
        });
    }
}
