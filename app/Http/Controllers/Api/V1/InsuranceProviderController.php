<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Insurance\Models\InsuranceProvider;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $providers = InsuranceProvider::query()
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($providers, $providers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:insurance_providers,code'],
            'name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email'],
            'coverage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $provider = InsuranceProvider::create($data);

        return ApiResponse::created($provider, 'Insurance provider created.');
    }

    public function show(InsuranceProvider $provider): JsonResponse
    {
        return ApiResponse::success($provider);
    }

    public function update(Request $request, InsuranceProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:insurance_providers,code,'.$provider->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email'],
            'coverage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $provider->update($data);

        return ApiResponse::success($provider, 'Insurance provider updated.');
    }

    public function destroy(InsuranceProvider $provider): JsonResponse
    {
        $provider->delete();

        return ApiResponse::success(null, 'Insurance provider deleted.');
    }
}
