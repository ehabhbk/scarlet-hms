<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HumanResources\Models\Employee;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 5), 100));

        return ApiResponse::paginated($employees, $employees);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_number' => ['required', 'string', 'max:40', 'unique:employees,employee_number'],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:60'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hired_on' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $employee = Employee::create($data);

        return ApiResponse::created($employee, 'Employee created.');
    }

    public function show(Employee $employee): JsonResponse
    {
        return ApiResponse::success($employee->load('user'));
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_number' => ['sometimes', 'string', 'max:40', 'unique:employees,employee_number,'.$employee->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:60'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hired_on' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $employee->update($data);

        return ApiResponse::success($employee, 'Employee updated.');
    }
}
