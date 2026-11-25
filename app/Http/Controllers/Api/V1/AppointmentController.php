<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Requests\CancelAppointmentRequest;
use App\Domains\Scheduling\Requests\CheckInAppointmentRequest;
use App\Domains\Scheduling\Requests\StoreAppointmentRequest;
use App\Domains\Scheduling\Services\AppointmentService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Appointment::query()
            ->with([
                'patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en,date_of_birth,gender,phone',
                'doctor:id,name,job_title',
                'department:id,name_ar,name_en',
                'branch:id,code,name_ar,name_en',
                'queueTicket',
            ]);

        if ($date = $request->string('date')->toString()) {
            $query->whereDate('scheduled_at', $date);
        }

        if ($from = $request->string('from')->toString()) {
            $query->where('scheduled_at', '>=', $from.' 00:00:00');
        }

        if ($to = $request->string('to')->toString()) {
            $query->where('scheduled_at', '<=', $to.' 23:59:59');
        }

        if ($status = $request->string('status')->toString()) {
            $statuses = explode(',', $status);
            $query->whereIn('status', $statuses);
        }

        if ($doctorId = $request->integer('doctor_id')) {
            $query->where('doctor_id', $doctorId);
        }

        if ($departmentId = $request->integer('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($branchId = $request->integer('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($search = $request->string('search')->toString()) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('appointment_number', 'like', $like)
                    ->orWhereHas('patient', function ($patient) use ($like) {
                        $patient->where('mrn', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('first_name_ar', 'like', $like)
                            ->orWhere('last_name_ar', 'like', $like)
                            ->orWhere('first_name_en', 'like', $like)
                            ->orWhere('last_name_en', 'like', $like);
                    });
            });
        }

        $sort = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $appointments = $query
            ->orderBy('scheduled_at', $sort)
            ->paginate(min(max($request->integer('per_page', 20), 5), 100))
            ->withQueryString();

        return ApiResponse::paginated($appointments, AppointmentResource::collection($appointments));
    }

    public function summary(Request $request): JsonResponse
    {
        $date = $request->string('date')->toString() ?: today()->toDateString();
        $query = Appointment::query()->whereDate('scheduled_at', $date);

        if ($branchId = $request->integer('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        $counts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return ApiResponse::success([
            'date' => $date,
            'total' => (clone $query)->count(),
            'counts' => $counts,
        ]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->appointmentService->book($request->validated(), $request->user());

        return ApiResponse::created(new AppointmentResource($appointment), __('appointments.booked'));
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load([
            'patient',
            'doctor:id,name',
            'department:id,name_ar,name_en',
            'branch:id,code,name_ar,name_en',
            'queueTicket',
        ]);

        return ApiResponse::success(new AppointmentResource($appointment));
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if(
            in_array($appointment->status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED], true),
            422,
            __('appointments.cannot_update')
        );

        $data = $request->validate([
            'scheduled_at' => ['sometimes', 'date'],
            'doctor_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'duration_minutes' => ['sometimes', 'integer', 'between:5,240'],
            'type' => ['sometimes', Rule::in(['new', 'follow_up', 'walk_in', 'consultation'])],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $appointment->update($data);

        return ApiResponse::success(
            new AppointmentResource($appointment->fresh()->load(['patient', 'doctor', 'department'])),
            __('appointments.updated')
        );
    }

    public function checkIn(CheckInAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $appointment = $this->appointmentService->checkIn(
            $appointment,
            $request->user(),
            $request->string('priority')->toString() ?: 'normal'
        );

        return ApiResponse::success(new AppointmentResource($appointment), __('appointments.checked_in'));
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $appointment = $this->appointmentService->cancel(
            $appointment,
            $request->string('reason')->toString(),
            $request->user()
        );

        return ApiResponse::success(new AppointmentResource($appointment), __('appointments.cancelled'));
    }

    public function noShow(Appointment $appointment): JsonResponse
    {
        $appointment = $this->appointmentService->noShow($appointment);

        return ApiResponse::success(new AppointmentResource($appointment), __('appointments.no_show_marked'));
    }
}
