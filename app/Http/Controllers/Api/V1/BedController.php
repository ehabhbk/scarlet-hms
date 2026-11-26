<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inpatient\Models\Bed;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $beds = Bed::query()->with('ward:id,name_ar,name_en')->orderBy('code')->paginate(min(max($request->integer('per_page', 25), 5), 100));
        return ApiResponse::paginated($beds, $beds);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ward_id' => ['required', 'integer', 'exists:wards,id'],
            'code' => ['required', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
        $bed = Bed::create($data);
        return ApiResponse::created($bed, 'Bed created.');
    }

    public function update(Request $request, Bed $bed): JsonResponse
    {
        $data = $request->validate([
            'ward_id' => ['sometimes', 'integer', 'exists:wards,id'],
            'code' => ['sometimes', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
        $bed->update($data);
        return ApiResponse::success($bed, 'Bed updated.');
    }
}
