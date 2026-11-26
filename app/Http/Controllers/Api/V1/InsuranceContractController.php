<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Insurance\Models\InsuranceContract;
use App\Domains\Insurance\Models\InsuranceContractService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contracts = InsuranceContract::query()
            ->with('provider:id,name,code')
            ->orderByDesc('created_at')
            ->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($contracts, $contracts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'insurance_provider_id' => ['required', 'integer', 'exists:insurance_providers,id'],
            'contract_number' => ['required', 'string', 'max:60', 'unique:insurance_contracts,contract_number'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'patient_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'company_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $contract = InsuranceContract::create($data);

        return ApiResponse::created($contract->load('provider'), 'Insurance contract created.');
    }

    public function show(InsuranceContract $contract): JsonResponse
    {
        return ApiResponse::success($contract->load(['provider', 'services']));
    }

    public function addService(Request $request, InsuranceContract $contract): JsonResponse
    {
        $data = $request->validate([
            'service_code' => ['required', 'string', 'max:60'],
            'service_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'patient_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'company_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $service = $contract->services()->create($data);

        return ApiResponse::created($service, 'Contract service added.');
    }
}
