<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Pharmacy\Models\Medication;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Medication::query();

        if ($search = $request->string('search')->toString()) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('generic_name', 'like', $like);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $medications = $query->orderBy('name_ar')->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($medications, $medications);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:medications,code'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'form' => ['nullable', 'string', 'max:40'],
            'strength' => ['nullable', 'string', 'max:40'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $medication = Medication::create($data);

        return ApiResponse::created($medication, 'Medication created.');
    }

    public function show(Medication $medication): JsonResponse
    {
        return ApiResponse::success($medication);
    }

    public function update(Request $request, Medication $medication): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:medications,code,'.$medication->id],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'form' => ['nullable', 'string', 'max:40'],
            'strength' => ['nullable', 'string', 'max:40'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $medication->update($data);

        return ApiResponse::success($medication, 'Medication updated.');
    }

    public function destroy(Medication $medication): JsonResponse
    {
        $medication->delete();

        return ApiResponse::success(null, 'Medication deleted.');
    }
}
