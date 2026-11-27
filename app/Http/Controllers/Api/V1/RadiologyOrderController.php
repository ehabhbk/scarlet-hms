<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Integration\Services\DevicePollerService;
use App\Domains\Insurance\Models\PatientInsurance;
use App\Domains\Radiology\Models\RadiologyDevice;
use App\Domains\Radiology\Models\RadiologyDeviceMessage;
use App\Domains\Radiology\Models\RadiologyImage;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RadiologyOrderController extends Controller
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly DevicePollerService $poller,
    ) {}

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

    /**
     * Dynamic worklist: one ring per real radiology order.
     *
     * Rings are derived from the orders themselves, never from a fixed list.
     * Status precedence: printed > approved > entered/received > empty.
     */
    public function worklist(): JsonResponse
    {
        $orders = RadiologyOrder::query()
            ->with([
                'patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en,national_id,gender,date_of_birth,phone,address,status,registration_branch_id,created_at',
                'patient.registrationBranch:id,code,name_ar,name_en',
                'doctor:id,name',
                'branch:id,name_ar,name_en',
                'study:id,code,name_ar,name_en,modality',
                'sourceDevice:id,code,name,modality',
                'reporter:id,name',
                'printer:id,name',
                'sourceMessage:id,status,parsed_summary,received_at',
            ])
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

        $rows = $orders->map(function (RadiologyOrder $order) use ($invoices, $policies): array {
            $invoice = $invoices->get($order->patient_id)?->first();
            $policy = $policies->get($order->patient_id)?->first();

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'ordered_at' => $order->ordered_at,
                'completed_at' => $order->completed_at,
                'result_received_at' => $order->result_received_at,
                'printed_at' => $order->printed_at,
                'patient' => $order->patient,
                'registration_branch' => $order->patient?->registrationBranch,
                'doctor' => $order->doctor,
                'branch' => $order->branch,
                'study' => $order->study,
                'study_description' => $order->study_description,
                'modality' => $order->modality,
                'source' => $order->source,
                'source_device' => $order->sourceDevice,
                'source_message' => $order->sourceMessage,
                'reporter' => $order->reporter,
                'printer' => $order->printer,
                'report' => $order->report,
                'result_status' => $this->resultStatus($order),
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
            ];
        })->values();

        return ApiResponse::success($rows, 'Radiology worklist prepared.');
    }

    public function show(RadiologyOrder $order): JsonResponse
    {
        return ApiResponse::success($order->load([
            'patient', 'doctor', 'branch', 'encounter', 'reporter', 'printer',
            'study', 'sourceDevice', 'sourceMessage', 'images.device:id,code,name',
        ]));
    }

    /**
     * Save a draft report without signing. Importing a device result never
     * signs it; a human must use confirmResult()/sign explicitly.
     */
    public function report(Request $request, RadiologyOrder $order): JsonResponse
    {
        abort_if($order->status === RadiologyOrder::STATUS_CANCELLED, 422, 'Cancelled orders cannot be edited.');
        abort_if($order->status === RadiologyOrder::STATUS_COMPLETED, 422, 'Signed reports cannot be edited. Reopen the report first.');

        $data = $request->validate([
            'report' => ['required', 'string'],
        ]);

        $order->update(['report' => $data['report']]);

        return ApiResponse::success($order->fresh(['reporter', 'study', 'sourceDevice']), 'تم حفظ التقرير دون توقيع.');
    }

    public function confirmResult(Request $request, RadiologyOrder $order): JsonResponse
    {
        abort_if($order->status === RadiologyOrder::STATUS_CANCELLED, 422, 'Cancelled orders cannot be confirmed.');
        abort_if($order->status === RadiologyOrder::STATUS_COMPLETED, 422, 'Report is already completed.');

        $data = $request->validate([
            'report' => ['nullable', 'string'],
        ]);

        $report = $data['report'] ?? $order->report;
        abort_unless(filled($report), 422, 'لا يمكن توقيع تقرير فارغ.');

        $order->update([
            'report' => $report,
            'status' => RadiologyOrder::STATUS_COMPLETED,
            'completed_at' => now(),
            'reported_by' => $request->user()->id,
            'printed_at' => null,
            'printed_by' => null,
        ]);

        return ApiResponse::success($order->fresh(['reporter', 'study', 'sourceDevice']), 'Device result confirmed and signed.');
    }

    /**
     * Cancel a signature so the report can be corrected and signed again.
     */
    public function reopen(RadiologyOrder $order): JsonResponse
    {
        abort_if($order->status !== RadiologyOrder::STATUS_COMPLETED, 422, 'Only signed reports can be reopened.');

        $order->update([
            'status' => $order->source === 'device'
                ? RadiologyOrder::STATUS_RESULT_RECEIVED
                : RadiologyOrder::STATUS_ORDERED,
            'reported_by' => null,
            'completed_at' => null,
            'printed_at' => null,
            'printed_by' => null,
        ]);

        return ApiResponse::success($order->fresh(['reporter', 'study', 'sourceDevice']), 'تم إلغاء الاعتماد — يمكن تعديل التقرير الآن.');
    }

    /**
     * Register that the signed report was printed. The HTML itself is rendered
     * by the client, exactly like the laboratory report workflow.
     */
    public function printOrder(Request $request, RadiologyOrder $order): JsonResponse
    {
        abort_if($order->status !== RadiologyOrder::STATUS_COMPLETED || ! filled($order->report), 422, 'لا يمكن طباعة التقرير إلا بعد كتابته وتوقيعه.');

        $order->update([
            'printed_at' => now(),
            'printed_by' => $request->user()->id,
        ]);

        return ApiResponse::success($order->fresh(['patient', 'doctor', 'branch', 'study', 'sourceDevice', 'reporter']), 'تم تسجيل طباعة التقرير.');
    }

    public function cancel(RadiologyOrder $order): JsonResponse
    {
        $order->update(['status' => RadiologyOrder::STATUS_CANCELLED]);

        return ApiResponse::success($order, 'Radiology order cancelled.');
    }

    /**
     * Devices mapped to this order through its study or modality. The UI never
     * asks the user to pick a device from a drop-down.
     */
    public function devices(RadiologyOrder $order): JsonResponse
    {
        return ApiResponse::success($this->mappedDevices($order)->values());
    }

    /**
     * Messages and DICOM objects already received for this order, plus the
     * devices that can be queried for it.
     */
    public function deviceData(RadiologyOrder $order): JsonResponse
    {
        return ApiResponse::success($this->deviceDataPayload($order), 'Radiology device data prepared.');
    }

    /**
     * Manually query the mapped devices for this order. Every received frame
     * goes through the normal ingest pipeline and is left unsigned.
     */
    public function fetchDevices(Request $request, RadiologyOrder $order): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:radiology_devices,id'],
        ]);

        $devices = $this->mappedDeviceModels($order);
        if (! empty($data['device_id'])) {
            $devices = $devices->where('id', (int) $data['device_id'])->values();
            abort_if($devices->isEmpty(), 422, 'الجهاز المحدد غير مرتبط بهذه الدراسة.');
        }

        $order->loadMissing(['patient:id,mrn', 'study:id,code,name_ar,name_en']);
        $context = [
            'order_number' => $order->order_number,
            'study_code' => $order->study?->code ?? $order->study_description,
            'patient_mrn' => $order->patient?->mrn,
        ];

        $polls = $devices->map(function (RadiologyDevice $device) use ($context): array {
            return [
                'device' => [
                    'id' => $device->id,
                    'code' => $device->code,
                    'name' => $device->name,
                ],
                'result' => $this->poller->poll($device, 'radiology', $context),
            ];
        })->values();

        return ApiResponse::success(
            array_merge($this->deviceDataPayload($order), ['polls' => $polls]),
            'تم استجواب الأجهزة — أي ردود واردة تنتظر المراجعة والتوقيع.',
        );
    }

    public function downloadImage(RadiologyImage $image): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(Storage::disk($image->disk)->exists($image->path), 404, 'ملف الصورة غير موجود.');

        return Storage::disk($image->disk)->download($image->path, basename($image->path), [
            'Content-Type' => $image->mime_type,
        ]);
    }

    private function resultStatus(RadiologyOrder $order): string
    {
        return match (true) {
            $order->status === RadiologyOrder::STATUS_CANCELLED => 'cancelled',
            $order->printed_at !== null => 'printed',
            $order->status === RadiologyOrder::STATUS_COMPLETED => 'approved',
            $order->status === RadiologyOrder::STATUS_RESULT_RECEIVED => 'received',
            filled($order->report) => 'entered',
            default => 'empty',
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, RadiologyDevice>
     */
    private function mappedDeviceModels(RadiologyOrder $order)
    {
        return RadiologyDevice::query()
            ->with('studies:id,code,name_ar,name_en,modality')
            ->where('status', 'active')
            ->where(function ($query) use ($order): void {
                if ($order->radiology_study_id) {
                    $query->whereHas('studies', fn ($q) => $q->where('radiology_studies.id', $order->radiology_study_id));
                }

                if ($order->modality) {
                    $query->orWhere('modality', $order->modality);
                }

                if (! $order->radiology_study_id && ! $order->modality) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Serialized devices with the reason they were mapped. Kept as plain
     * arrays so poll/save operations never persist this UI-only attribute.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mappedDevices(RadiologyOrder $order): array
    {
        return $this->mappedDeviceModels($order)
            ->map(function (RadiologyDevice $device) use ($order): array {
                $data = $device->toArray();
                $byStudy = $order->radiology_study_id
                    && $device->studies->contains('id', $order->radiology_study_id);
                $data['mapped_reason'] = $byStudy ? 'study' : 'modality';

                return $data;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{order: RadiologyOrder, devices: mixed, messages: mixed, images: mixed}
     */
    private function deviceDataPayload(RadiologyOrder $order): array
    {
        $order->loadMissing([
            'patient:id,mrn',
            'study:id,code,name_ar,name_en,modality',
            'sourceDevice:id,code,name,modality',
            'sourceMessage:id,status,parsed_summary,received_at',
            'images.device:id,code,name',
        ]);

        $terms = array_filter([
            $order->order_number,
            $order->patient?->mrn,
            $order->study?->code,
        ]);

        $messages = RadiologyDeviceMessage::query()
            ->with('device:id,code,name')
            ->where(function ($query) use ($order, $terms): void {
                $query->where('id', $order->source_message_id);

                foreach ($terms as $term) {
                    $escaped = addcslashes((string) $term, '%_\\');
                    $query->orWhere('raw_message', 'like', "%{$escaped}%")
                        ->orWhere('parsed_summary', 'like', "%{$escaped}%");
                }
            })
            ->orderByDesc('received_at')
            ->limit(30)
            ->get();

        return [
            'order' => $order,
            'devices' => $this->mappedDevices($order),
            'messages' => $messages,
            'images' => $order->images,
        ];
    }
}
