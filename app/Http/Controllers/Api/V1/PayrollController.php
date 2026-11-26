<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HumanResources\Models\Employee;
use App\Domains\HumanResources\Models\PayrollRun;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $runs = PayrollRun::query()->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 20), 5), 100));
        return ApiResponse::paginated($runs, $runs);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'max:20'],
        ]);

        $total = Employee::query()->where('status', 'active')->sum('basic_salary');

        $run = PayrollRun::create([
            'period' => $data['period'],
            'total_amount' => $total,
            'status' => 'draft',
        ]);

        return ApiResponse::created($run, 'Payroll run generated.');
    }

    public function markPaid(PayrollRun $run): JsonResponse
    {
        $run->update(['status' => 'paid', 'paid_at' => now()]);
        return ApiResponse::success($run, 'Payroll marked as paid.');
    }
}
