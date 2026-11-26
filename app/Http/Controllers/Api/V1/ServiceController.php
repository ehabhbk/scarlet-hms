<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Services\Models\Service;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name_ar', 'like', '%'.$s.'%')->orWhere('name_en', 'like', '%'.$s.'%')->orWhere('code', 'like', '%'.$s.'%'))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name_ar')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($services, $services);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:services,code'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $service = Service::create($data);
        return ApiResponse::created($service, 'Service created.');
    }

    public function show(Service $service): JsonResponse
    {
        return ApiResponse::success($service);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:services,code,'.$service->id],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $service->update($data);
        return ApiResponse::success($service, 'Service updated.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();
        return ApiResponse::success(null, 'Service deleted.');
    }
}
