<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Laboratory\Models\LabTest;
use App\Domains\Laboratory\Models\LabTestParameter;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabTestParameterController extends Controller
{
    public function index(Request $request, LabTest $test): JsonResponse
    {
        return ApiResponse::success($test->parameters()->orderBy('sort_order')->get());
    }

    public function store(Request $request, LabTest $test): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:30'],
            'reference_range' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $param = $test->parameters()->create($data);

        return ApiResponse::created($param, 'Parameter created.');
    }

    public function update(Request $request, LabTestParameter $parameter): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40'],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:30'],
            'reference_range' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $parameter->update($data);
        return ApiResponse::success($parameter, 'Parameter updated.');
    }

    public function destroy(LabTestParameter $parameter): JsonResponse
    {
        $parameter->delete();
        return ApiResponse::success(null, 'Parameter deleted.');
    }
}
