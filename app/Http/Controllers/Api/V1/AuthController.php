<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Requests\ChangePasswordRequest;
use App\Domains\Identity\Requests\LoginRequest;
use App\Domains\Identity\Requests\UpdatePreferencesRequest;
use App\Domains\Identity\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('identifier')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name')->toString() ?: 'web'
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
        ], __('auth.logged_in'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions', 'branch', 'department', 'branches']);

        return ApiResponse::success(new UserResource($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(null, __('auth.logged_out'));
    }

    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = $this->authService->updatePreferences($request->user(), $request->validated());

        return ApiResponse::success(new UserResource($user), __('messages.preferences_updated'));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString()
        );

        return ApiResponse::success(null, __('auth.password_changed'));
    }
}
