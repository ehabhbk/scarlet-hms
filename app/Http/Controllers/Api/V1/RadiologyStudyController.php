<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Radiology\Models\RadiologyStudy;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RadiologyStudyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $studies = RadiologyStudy::query()
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name_ar', 'like', '%'.$s.'%')->orWhere('name_en', 'like', '%'.$s.'%')->orWhere('code', 'like', '%'.$s.'%'))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name_ar')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($studies, $studies);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:radiology_studies,code'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'modality' => ['nullable', 'string', 'max:40'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $study = RadiologyStudy::create($data);
        return ApiResponse::created($study, 'Radiology study created.');
    }

    public function show(RadiologyStudy $study): JsonResponse
    {
        return ApiResponse::success($study);
    }

    public function update(Request $request, RadiologyStudy $study): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:radiology_studies,code,'.$study->id],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'modality' => ['nullable', 'string', 'max:40'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $study->update($data);
        return ApiResponse::success($study, 'Radiology study updated.');
    }

    public function destroy(RadiologyStudy $study): JsonResponse
    {
        $study->delete();
        return ApiResponse::success(null, 'Radiology study deleted.');
    }
}
