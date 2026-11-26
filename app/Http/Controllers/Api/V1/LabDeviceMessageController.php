<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integration\Services\DeviceMessageIngestService;
use App\Domains\Laboratory\Models\LabDeviceMessage;
use App\Domains\Laboratory\Models\LabOrder;
use App\Domains\Laboratory\Models\LabOrderItem;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabDeviceMessageController extends Controller
{
    public function __construct(private readonly DeviceMessageIngestService $ingest) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $query = LabDeviceMessage::query()
            ->with('device:id,code,name,protocol')
            ->when($request->integer('lab_device_id'), fn ($q, $id) => $q->where('lab_device_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('parsed_summary', 'like', '%'.$search.'%')
                    ->orWhere('raw_message', 'like', '%'.$search.'%');
            }));

        $messages = $query->orderByDesc('received_at')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($messages, $messages);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lab_device_id' => ['required', 'integer', 'exists:lab_devices,id'],
            'raw_message' => ['required', 'string'],
            'protocol' => ['nullable', 'string', 'max:40'],
        ]);

        $message = $this->ingest->ingestLab($data['raw_message'], [
            'device_id' => $data['lab_device_id'],
            'protocol' => $data['protocol'] ?? null,
        ]);

        return ApiResponse::created($message, 'Device message received and processed.');
    }

    public function show(LabDeviceMessage $message): JsonResponse
    {
        return ApiResponse::success($message->load('device:id,code,name,protocol'));
    }

    public function values(Request $request, LabDeviceMessage $message): JsonResponse
    {
        $data = $request->validate([
            'lab_order_item_id' => ['required', 'integer', 'exists:lab_order_items,id'],
        ]);

        $item = LabOrderItem::query()
            ->with('test.parameters')
            ->findOrFail($data['lab_order_item_id']);

        return ApiResponse::success(
            $this->ingest->valuesForItem($message, $item),
            'Device values extracted for review.',
        );
    }

    /**
     * Ready values for one test, selected by sample number and mapped device.
     */
    public function itemValues(LabOrderItem $item): JsonResponse
    {
        $result = $this->ingest->readyValuesForItem($item);

        return ApiResponse::success(
            $result,
            $result === null ? 'لا توجد نتيجة جاهزة لهذا الفحص من جهازه المخصص.' : 'قيم الجهاز جاهزة للمراجعة.',
        );
    }

    /**
     * All ready values for one sample, each from its own mapped device.
     */
    public function orderValues(LabOrder $order): JsonResponse
    {
        $results = $this->ingest->readyValuesForOrder($order);

        return ApiResponse::success(
            $results,
            $results === [] ? 'لا توجد نتائج جاهزة من الأجهزة لهذه العينة.' : 'تم تجميع نتائج الأجهزة للمراجعة.',
        );
    }

    public function reprocess(LabDeviceMessage $message): JsonResponse
    {
        $message = $this->ingest->reprocessLabMessage($message);

        return ApiResponse::success($message, 'Lab device message reprocessed.');
    }
}
