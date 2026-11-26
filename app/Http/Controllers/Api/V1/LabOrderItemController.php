<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Laboratory\Models\LabOrderItem;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabOrderItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LabOrderItem::query()
            ->with([
                'test.parameters',
                'values.parameter',
                'order:id,order_number,patient_id,doctor_id,branch_id,status,ordered_at',
                'order.patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en',
                'order.doctor:id,name',
                'sampleCollectorUser:id,name',
                'enteredByUser:id,name',
                'approvedByUser:id,name',
                'sourceDevice:id,code,name,protocol',
            ]);

        $sampleStatus = $request->string('sample_status')->toString();
        if ($sampleStatus === 'pending') {
            $query->whereNull('sample_collected_at');
        } elseif ($sampleStatus === 'collected') {
            $query->whereNotNull('sample_collected_at');
        }

        $resultStatus = $request->string('result_status')->toString();
        if ($resultStatus === 'pending') {
            $query->whereNotNull('sample_collected_at')->where('status', LabOrderItem::STATUS_PENDING);
        } elseif ($resultStatus === 'entered') {
            $query->where('status', LabOrderItem::STATUS_ENTERED);
        } elseif ($resultStatus === 'approved') {
            $query->where('status', LabOrderItem::STATUS_APPROVED);
        }

        if ($orderId = $request->integer('lab_order_id')) {
            $query->where('lab_order_id', $orderId);
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->whereHas('order', fn ($q) => $q->where('patient_id', $patientId));
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('test', fn ($t) => $t->where('name_ar', 'like', '%'.$search.'%')
                    ->orWhere('name_en', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%'))
                    ->orWhereHas('order.patient', fn ($p) => $p->where('mrn', 'like', '%'.$search.'%')
                        ->orWhere('first_name_ar', 'like', '%'.$search.'%')
                        ->orWhere('last_name_ar', 'like', '%'.$search.'%'));
            });
        }

        $items = $query->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($items, $items);
    }
}
