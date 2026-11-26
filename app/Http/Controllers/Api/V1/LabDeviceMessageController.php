<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integration\Services\DeviceMessageIngestService;
use App\Domains\Laboratory\Models\LabDeviceMessage;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabDeviceMessageController extends Controller
{
    public function __construct(private readonly DeviceMessageIngestService $ingest) {}

    public function index(Request $request): JsonResponse
    {
        $query = LabDeviceMessage::query()
            ->with('device:id,code,name')
            ->when($request->integer('lab_device_id'), fn ($q, $id) => $q->where('lab_device_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status));

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

    public function reprocess(LabDeviceMessage $message): JsonResponse
    {
        $message = $this->ingest->reprocessLabMessage($message);

        return ApiResponse::success($message, 'Lab device message reprocessed.');
    }
}
