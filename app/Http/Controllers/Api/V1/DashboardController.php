<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Dashboard\Services\DashboardService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function summary(Request $request): JsonResponse
    {
        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId && ! in_array($branchId, $request->user()->accessibleBranchIds(), true)) {
            $branchId = $request->user()->branch_id;
        }

        return ApiResponse::success(
            $this->dashboardService->summary($request->user(), $branchId)
        );
    }
}
