<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integration\Services\DeviceConnectionTester;
use App\Domains\Laboratory\Models\LabDevice;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = LabDevice::query()
            ->with('tests:id,code,name_ar,name_en')
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($devices, $devices);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:lab_devices,code'],
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'protocol' => ['nullable', 'string', 'max:40'],
            'host' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'status' => ['nullable', 'string', 'max:20'],
            'test_ids' => ['nullable', 'array'],
            'test_ids.*' => ['integer', 'exists:lab_tests,id'],
        ]);

        $device = LabDevice::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'manufacturer' => $data['manufacturer'] ?? null,
            'model' => $data['model'] ?? null,
            'protocol' => $data['protocol'] ?? 'HL7',
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        if (! empty($data['test_ids'])) {
            $device->tests()->sync($data['test_ids']);
        }

        return ApiResponse::created($device->load('tests'), 'Lab device registered.');
    }

    public function show(LabDevice $device): JsonResponse
    {
        return ApiResponse::success($device->load('tests'));
    }

    public function update(Request $request, LabDevice $device): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:lab_devices,code,'.$device->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'protocol' => ['nullable', 'string', 'max:40'],
            'host' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'status' => ['nullable', 'string', 'max:20'],
            'test_ids' => ['nullable', 'array'],
            'test_ids.*' => ['integer', 'exists:lab_tests,id'],
        ]);

        $device->update([
            'code' => $data['code'] ?? $device->code,
            'name' => $data['name'] ?? $device->name,
            'manufacturer' => $data['manufacturer'] ?? $device->manufacturer,
            'model' => $data['model'] ?? $device->model,
            'protocol' => $data['protocol'] ?? $device->protocol,
            'host' => array_key_exists('host', $data) ? $data['host'] : $device->host,
            'port' => array_key_exists('port', $data) ? $data['port'] : $device->port,
            'status' => $data['status'] ?? $device->status,
        ]);

        if (array_key_exists('test_ids', $data)) {
            $device->tests()->sync($data['test_ids'] ?? []);
        }

        return ApiResponse::success($device->load('tests'), 'Lab device updated.');
    }

    public function destroy(LabDevice $device): JsonResponse
    {
        $device->tests()->detach();
        $device->delete();

        return ApiResponse::success(null, 'Lab device deleted.');
    }

    public function testConnection(LabDevice $device, DeviceConnectionTester $tester): JsonResponse
    {
        $result = $tester->test($device->host, $device->port);

        return $result['ok']
            ? ApiResponse::success($result, $result['message'])
            : ApiResponse::error($result['message'], $result, 422, 'device_unreachable');
    }
}
