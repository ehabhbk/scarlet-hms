<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integration\Services\DeviceMessageIngestService;
use App\Domains\Radiology\Models\RadiologyDeviceMessage;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RadiologyDeviceMessageController extends Controller
{
    public function __construct(private readonly DeviceMessageIngestService $ingest) {}

    public function index(Request $request): JsonResponse
    {
        $query = RadiologyDeviceMessage::query()
            ->with('device:id,code,name')
            ->when($request->integer('radiology_device_id'), fn ($q, $id) => $q->where('radiology_device_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('search')->toString(), function ($q, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $q->where(function ($inner) use ($escaped): void {
                    $inner->where('raw_message', 'like', "%{$escaped}%")
                        ->orWhere('parsed_summary', 'like', "%{$escaped}%")
                        ->orWhere('message_id', 'like', "%{$escaped}%");
                });
            });

        $messages = $query->orderByDesc('received_at')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($messages, $messages);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'radiology_device_id' => ['required', 'integer', 'exists:radiology_devices,id'],
            'raw_message' => ['required', 'string'],
            'protocol' => ['nullable', 'string', 'max:40'],
        ]);

        $message = $this->ingest->ingestRadiology($data['raw_message'], [
            'device_id' => $data['radiology_device_id'],
            'protocol' => $data['protocol'] ?? null,
        ]);

        return ApiResponse::created($message, 'Radiology device message received and processed.');
    }

    public function reprocess(RadiologyDeviceMessage $message): JsonResponse
    {
        $message = $this->ingest->reprocessRadiologyMessage($message);

        return ApiResponse::success($message, 'Radiology device message reprocessed.');
    }
}
