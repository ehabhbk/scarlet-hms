<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceItem;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Refund;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Sequences\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(private readonly SequenceService $sequences) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en', 'branch:id,name_ar,name_en']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        $invoices = $query->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 20), 5), 100));

        return ApiResponse::paginated($invoices, $invoices);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.code' => ['nullable', 'string', 'max:40'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $invoice = DB::transaction(function () use ($data, $request) {
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += $item['unit_price'] * $item['quantity'];
            }

            $discount = $data['discount'] ?? 0;
            $tax = $data['tax'] ?? 0;
            $total = $subtotal - $discount + $tax;

            $invoice = Invoice::create([
                'invoice_number' => $this->sequences->next('invoice', 'INV', 6),
                'patient_id' => $data['patient_id'],
                'branch_id' => $data['branch_id'],
                'department_id' => $data['department_id'] ?? null,
                'encounter_id' => $data['encounter_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'status' => Invoice::STATUS_DRAFT,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid' => 0,
                'balance' => $total,
                'currency' => $data['currency'] ?? 'SDG',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'code' => $item['code'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total' => $item['unit_price'] * $item['quantity'],
                ]);
            }

            return $invoice;
        });

        $invoice->load(['patient', 'items']);

        return ApiResponse::created($invoice, 'Invoice created.');
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['patient', 'branch', 'department', 'encounter', 'appointment', 'items', 'payments.collector', 'refunds']);

        return ApiResponse::success($invoice);
    }

    public function issue(Invoice $invoice): JsonResponse
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            return ApiResponse::error('Only draft invoices can be issued.', 422);
        }

        $invoice->update([
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        return ApiResponse::success($invoice->fresh(['items']), 'Invoice issued.');
    }

    public function payment(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return ApiResponse::error('Cannot collect payment for a cancelled invoice.', 422);
        }

        $payment = DB::transaction(function () use ($data, $invoice, $request) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_number' => $this->sequences->next('payment', 'PAY', 6),
                'method' => $data['method'],
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
                'collected_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->increment('paid', $data['amount']);
            $invoice->decrement('balance', $data['amount']);
            $invoice->refresh();

            if ($invoice->balance <= 0) {
                $invoice->update(['status' => Invoice::STATUS_PAID]);
            } elseif ($invoice->paid > 0) {
                $invoice->update(['status' => Invoice::STATUS_PARTIAL]);
            }

            return $payment;
        });

        return ApiResponse::created($payment, 'Payment collected.');
    }

    public function refund(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string'],
        ]);

        $refund = Refund::create([
            'invoice_id' => $invoice->id,
            'payment_id' => $data['payment_id'] ?? null,
            'amount' => $data['amount'],
            'reason' => $data['reason'] ?? null,
            'status' => Refund::STATUS_PENDING,
        ]);

        return ApiResponse::created($refund, 'Refund requested.');
    }

    public function approveRefund(Request $request, Refund $refund): JsonResponse
    {
        $refund->update([
            'status' => Refund::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $refund->invoice->decrement('paid', $refund->amount);
        $refund->invoice->increment('balance', $refund->amount);
        $refund->invoice->refresh();
        if ($refund->invoice->balance > 0 && $refund->invoice->paid > 0) {
            $refund->invoice->update(['status' => Invoice::STATUS_PARTIAL]);
        } elseif ($refund->invoice->paid <= 0) {
            $refund->invoice->update(['status' => Invoice::STATUS_ISSUED]);
        }

        return ApiResponse::success($refund, 'Refund approved.');
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            return ApiResponse::error('Cannot cancel a paid invoice.', 422);
        }

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        return ApiResponse::success($invoice, 'Invoice cancelled.');
    }
}
