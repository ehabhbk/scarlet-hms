<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integration\Models\ClinicalDevice;
use App\Domains\Integration\Models\ClinicalDeviceMessage;
use App\Domains\Integration\Models\DevicePollLog;
use App\Domains\Integration\Services\DeviceConnectionTester;
use App\Domains\Integration\Services\DevicePollerService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicalDeviceController extends Controller
{
    private const CATEGORIES = ['emergency', 'operating_room', 'icu', 'ward', 'other'];

    public function index(Request $request): JsonResponse
    {
        $devices = ClinicalDevice::query()
            ->when($request->string('category')->toString(), fn ($q, $category) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 50), 5), 100));

        return ApiResponse::paginated($devices, $devices);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateDevice($request);
        $device = ClinicalDevice::query()->create($data);

        return ApiResponse::created($device, 'تم تسجيل الجهاز السريري.');
    }

    public function show(ClinicalDevice $device): JsonResponse
    {
        return ApiResponse::success($device->loadCount('messages'));
    }

    public function update(Request $request, ClinicalDevice $device): JsonResponse
    {
        $data = $this->validateDevice($request, $device);
        $device->update($data);

        return ApiResponse::success($device, 'تم تحديث الجهاز السريري.');
    }

    public function destroy(ClinicalDevice $device): JsonResponse
    {
        $device->delete();

        return ApiResponse::success(null, 'تم حذف الجهاز السريري.');
    }

    public function testConnection(ClinicalDevice $device, DeviceConnectionTester $tester): JsonResponse
    {
        $result = $tester->test($device->host, $device->port);

        return $result['ok']
            ? ApiResponse::success($result, $result['message'])
            : ApiResponse::error($result['message'], $result, 422, 'device_unreachable');
    }

    public function pollNow(ClinicalDevice $device, DevicePollerService $poller): JsonResponse
    {
        $result = $poller->poll($device, 'clinical');

        return $result['ok']
            ? ApiResponse::success($result, 'تم استجواب الجهاز وحفظ الردود للمراجعة.')
            : ApiResponse::error($result['error'] ?? 'تعذر استجواب الجهاز.', $result, 422, 'device_poll_failed');
    }

    public function messages(Request $request, ClinicalDevice $device): JsonResponse
    {
        $messages = ClinicalDeviceMessage::query()
            ->where('clinical_device_id', $device->id)
            ->when($request->string('search')->toString(), fn ($q, $search) => $q
                ->where('patient_mrn', 'like', '%'.$search.'%')
                ->orWhere('reference', 'like', '%'.$search.'%')
                ->orWhere('parsed_summary', 'like', '%'.$search.'%'))
            ->latest('received_at')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($messages, $messages);
    }

    public function logs(ClinicalDevice $device): JsonResponse
    {
        $logs = DevicePollLog::query()
            ->where('device_type', 'clinical')
            ->where('device_id', $device->id)
            ->orderByDesc('polled_at')
            ->limit(50)
            ->get();

        return ApiResponse::success($logs);
    }

    /** @return array<string, mixed> */
    private function validateDevice(Request $request, ? ClinicalDevice $device = null): array
    {
        $id = $device?->id ?? 'NULL';

        return $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:clinical_devices,code,'.$id],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'protocol' => ['nullable', 'string', 'max:40'],
            'host' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'direction' => ['nullable', 'string', 'in:push,pull,bidirectional'],
            'poll_enabled' => ['nullable', 'boolean'],
            'poll_interval_seconds' => ['nullable', 'integer', 'min:5', 'max:86400'],
            'poll_query' => ['nullable', 'string', 'max:500'],
            'poll_query_encoding' => ['nullable', 'string', 'in:literal,hex,base64'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);
    }
}
