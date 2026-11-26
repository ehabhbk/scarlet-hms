<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InsuranceClaimController extends Controller
{
    public function __construct(private readonly SequenceService $sequences) {}

    public function index(Request $request): JsonResponse
    {
        $query = InsuranceClaim::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar', 'provider', 'invoice:id,invoice_number']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        $claims = $query->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($claims, $claims);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'insurance_provider_id' => ['required', 'integer', 'exists:insurance_providers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'claimed_amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        $claim = InsuranceClaim::create([
            'claim_number' => $this->sequences->next('insurance_claim', 'CLM', 6),
            'patient_id' => $data['patient_id'],
            'insurance_provider_id' => $data['insurance_provider_id'],
            'invoice_id' => $data['invoice_id'] ?? null,
            'claimed_amount' => $data['claimed_amount'],
            'approved_amount' => 0,
            'status' => InsuranceClaim::STATUS_SUBMITTED,
            'notes' => $data['notes'] ?? null,
            'submitted_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created($claim->load(['patient', 'provider', 'invoice']), 'Insurance claim submitted.');
    }

    public function show(InsuranceClaim $claim): JsonResponse
    {
        return ApiResponse::success($claim->load(['patient', 'provider', 'invoice', 'createdBy']));
    }

    public function approve(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $data = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $claim->update([
            'status' => InsuranceClaim::STATUS_APPROVED,
            'approved_amount' => $data['approved_amount'],
            'decided_at' => now(),
        ]);

        return ApiResponse::success($claim, 'Insurance claim approved.');
    }

    public function reject(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $claim->update([
            'status' => InsuranceClaim::STATUS_REJECTED,
            'decided_at' => now(),
        ]);

        return ApiResponse::success($claim, 'Insurance claim rejected.');
    }
}
