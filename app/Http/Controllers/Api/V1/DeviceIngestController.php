<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integration\Services\DeviceMessageIngestService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceIngestController extends Controller
{
    public function __construct(private readonly DeviceMessageIngestService $ingest) {}

    public function lab(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return ApiResponse::error('Invalid device token.', null, 401, 'invalid_device_token');
        }

        $data = $request->validate([
            'raw_message' => ['required', 'string'],
            'lab_device_id' => ['nullable', 'integer', 'exists:lab_devices,id'],
            'device_code' => ['nullable', 'string', 'max:40'],
            'protocol' => ['nullable', 'string', 'max:40'],
        ]);

        $message = $this->ingest->ingestLab($data['raw_message'], [
            'device_id' => $data['lab_device_id'] ?? null,
            'device_code' => $data['device_code'] ?? null,
            'protocol' => $data['protocol'] ?? null,
        ]);

        return ApiResponse::success([
            'id' => $message->id,
            'status' => $message->status,
            'device' => $message->device,
            'parsed_summary' => $message->parsed_summary,
        ], 'Lab device message processed.');
    }

    public function radiology(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return ApiResponse::error('Invalid device token.', null, 401, 'invalid_device_token');
        }

        $data = $request->validate([
            'raw_message' => ['required', 'string'],
            'radiology_device_id' => ['nullable', 'integer', 'exists:radiology_devices,id'],
            'device_code' => ['nullable', 'string', 'max:40'],
            'protocol' => ['nullable', 'string', 'max:40'],
        ]);

        $message = $this->ingest->ingestRadiology($data['raw_message'], [
            'device_id' => $data['radiology_device_id'] ?? null,
            'device_code' => $data['device_code'] ?? null,
            'protocol' => $data['protocol'] ?? null,
        ]);

        return ApiResponse::success([
            'id' => $message->id,
            'status' => $message->status,
            'device' => $message->device,
            'parsed_summary' => $message->parsed_summary,
        ], 'Radiology device message processed.');
    }

    private function authorized(Request $request): bool
    {
        $expected = (string) config('devices.ingest_token', '');
        $provided = (string) $request->header('X-Device-Token', '');

        return $expected !== '' && hash_equals($expected, $provided);
    }
}
