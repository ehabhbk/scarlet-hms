<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inpatient\Models\Ward;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wards = Ward::query()->with('branch:id,name_ar,name_en')->orderBy('name_ar')->paginate(min(max($request->integer('per_page', 25), 5), 100));
        return ApiResponse::paginated($wards, $wards);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:40'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);
        $ward = Ward::create($data);
        return ApiResponse::created($ward, 'Ward created.');
    }

    public function show(Ward $ward): JsonResponse
    {
        return ApiResponse::success($ward->load(['branch', 'beds']));
    }

    public function update(Request $request, Ward $ward): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
            'code' => ['sometimes', 'string', 'max:40'],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);
        $ward->update($data);
        return ApiResponse::success($ward, 'Ward updated.');
    }
}
