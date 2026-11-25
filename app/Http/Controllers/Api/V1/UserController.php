<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Requests\StoreUserRequest;
use App\Domains\Identity\Requests\UpdateUserRequest;
use App\Domains\Identity\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['roles', 'branch:id,code,name_ar,name_en', 'department:id,name_ar,name_en']);

        if ($search = $request->string('search')->toString()) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($role = $request->string('role')->toString()) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($branchId = $request->integer('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        if ($departmentId = $request->integer('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 20), 5), 100))
            ->withQueryString();

        return ApiResponse::paginated($users, UserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return ApiResponse::created(new UserResource($user), __('users.created'));
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($user->load(['roles.permissions', 'branch', 'department', 'branches']))
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->update($user, $request->validated());

        return ApiResponse::success(new UserResource($user), __('users.updated'));
    }

    public function deactivate(User $user): JsonResponse
    {
        $user = $this->userService->deactivate($user);

        return ApiResponse::success(new UserResource($user), __('users.deactivated'));
    }

    public function roles(): JsonResponse
    {
        return ApiResponse::success(
            \App\Http\Resources\RoleResource::collection(
                Role::query()->withCount(['permissions', 'users'])->orderBy('name_en')->get()
            )
        );
    }
}
