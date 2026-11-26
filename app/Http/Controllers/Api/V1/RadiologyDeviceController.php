<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integration\Services\DeviceConnectionTester;
use App\Domains\Radiology\Models\RadiologyDevice;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RadiologyDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = RadiologyDevice::query()
            ->with('studies:id,code,name_ar,name_en,modality')
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($devices, $devices);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:radiology_devices,code'],
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'protocol' => ['nullable', 'string', 'max:40'],
            'ae_title' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9_-]*$/'],
            'host' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'modality' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:20'],
            'study_ids' => ['nullable', 'array'],
            'study_ids.*' => ['integer', 'exists:radiology_studies,id'],
        ]);

        $device = RadiologyDevice::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'manufacturer' => $data['manufacturer'] ?? null,
            'model' => $data['model'] ?? null,
            'protocol' => $data['protocol'] ?? 'HL7',
            'ae_title' => $data['ae_title'] ?? null,
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'modality' => $data['modality'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        if (! empty($data['study_ids'])) {
            $device->studies()->sync($data['study_ids']);
        }

        return ApiResponse::created($device->load('studies'), 'Radiology device registered.');
    }

    public function show(RadiologyDevice $device): JsonResponse
    {
        return ApiResponse::success($device->load('studies'));
    }

    public function update(Request $request, RadiologyDevice $device): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:radiology_devices,code,'.$device->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'protocol' => ['nullable', 'string', 'max:40'],
            'ae_title' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9_-]*$/'],
            'host' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'modality' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:20'],
            'study_ids' => ['nullable', 'array'],
            'study_ids.*' => ['integer', 'exists:radiology_studies,id'],
        ]);

        $device->update([
            'code' => $data['code'] ?? $device->code,
            'name' => $data['name'] ?? $device->name,
            'manufacturer' => $data['manufacturer'] ?? $device->manufacturer,
            'model' => $data['model'] ?? $device->model,
            'protocol' => $data['protocol'] ?? $device->protocol,
            'ae_title' => array_key_exists('ae_title', $data) ? $data['ae_title'] : $device->ae_title,
            'host' => array_key_exists('host', $data) ? $data['host'] : $device->host,
            'port' => array_key_exists('port', $data) ? $data['port'] : $device->port,
            'modality' => array_key_exists('modality', $data) ? $data['modality'] : $device->modality,
            'status' => $data['status'] ?? $device->status,
        ]);

        if (array_key_exists('study_ids', $data)) {
            $device->studies()->sync($data['study_ids'] ?? []);
        }

        return ApiResponse::success($device->load('studies'), 'Radiology device updated.');
    }

    public function destroy(RadiologyDevice $device): JsonResponse
    {
        $device->studies()->detach();
        $device->delete();

        return ApiResponse::success(null, 'Radiology device deleted.');
    }

    public function testConnection(RadiologyDevice $device, DeviceConnectionTester $tester): JsonResponse
    {
        $result = $tester->test($device->host, $device->port);

        return $result['ok']
            ? ApiResponse::success($result, $result['message'])
            : ApiResponse::error($result['message'], $result, 422);
    }
}
