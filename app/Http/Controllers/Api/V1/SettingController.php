<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Settings\Models\Setting;
use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Setting::query()->orderBy('group')->orderBy('key');

        if ($group = $request->string('group')->toString()) {
            $query->where('group', $group);
        }

        $settings = $query->get();

        return ApiResponse::success([
            'settings' => SettingResource::collection($settings),
            'groups' => $settings->pluck('group')->unique()->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.group' => ['sometimes', 'string', 'max:60'],
            'settings.*.value' => ['nullable'],
            'settings.*.is_public' => ['sometimes', 'boolean'],
        ]);

        foreach ($data['settings'] as $key => $payload) {
            Setting::put(
                $key,
                $payload['value'] ?? null,
                $payload['group'] ?? 'general',
                (bool) ($payload['is_public'] ?? false)
            );
        }

        return ApiResponse::success(
            SettingResource::collection(Setting::query()->orderBy('group')->orderBy('key')->get()),
            __('settings.updated')
        );
    }
}
