<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Laboratory\Models\LabTest;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabTestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tests = LabTest::query()
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name_ar', 'like', '%'.$s.'%')->orWhere('name_en', 'like', '%'.$s.'%')->orWhere('code', 'like', '%'.$s.'%'))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name_ar')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($tests, $tests);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:lab_tests,code'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:30'],
            'reference_range' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $test = LabTest::create($data);

        return ApiResponse::created($test, 'Lab test created.');
    }

    public function show(LabTest $test): JsonResponse
    {
        return ApiResponse::success($test);
    }

    public function update(Request $request, LabTest $test): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:lab_tests,code,'.$test->id],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:30'],
            'reference_range' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $test->update($data);

        return ApiResponse::success($test, 'Lab test updated.');
    }

    public function destroy(LabTest $test): JsonResponse
    {
        $test->delete();

        return ApiResponse::success(null, 'Lab test deleted.');
    }
}
