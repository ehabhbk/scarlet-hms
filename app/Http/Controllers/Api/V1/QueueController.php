<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Scheduling\Requests\SkipQueueRequest;
use App\Domains\Scheduling\Services\QueueService;
use App\Http\Controllers\Controller;
use App\Http\Resources\QueueTicketResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QueueController extends Controller
{
    public function __construct(private readonly QueueService $queueService) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = $request->integer('branch_id') ?: $request->user()->branch_id;

        abort_if($branchId === null, 422, __('queues.branch_required'));

        $board = $this->queueService->board(
            $branchId,
            $request->integer('department_id') ?: null,
            $request->string('date')->toString() ?: null
        );

        $board['tickets'] = QueueTicketResource::collection($board['tickets']);

        return ApiResponse::success($board);
    }

    public function publicBoard(Request $request, int $branch): JsonResponse
    {
        return ApiResponse::success(
            $this->queueService->publicBoard(
                $branch,
                $request->integer('department_id') ?: null,
                $request->string('date')->toString() ?: null
            )
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', Rule::in(['normal', 'urgent', 'emergency'])],
        ]);

        $ticket = $this->queueService->issue(
            patient: Patient::query()->findOrFail($data['patient_id']),
            branchId: $data['branch_id'],
            departmentId: $data['department_id'],
            doctorId: $data['doctor_id'] ?? null,
            actor: $request->user(),
            priority: $data['priority'] ?? 'normal'
        );

        return ApiResponse::created(new QueueTicketResource($ticket), __('queues.issued'));
    }

    public function call(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->call($ticket, $request->user());

        return ApiResponse::success(new QueueTicketResource($ticket), __('queues.called'));
    }

    public function next(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket = $this->queueService->callNext(
            $data['branch_id'],
            $data['department_id'],
            $request->user(),
            $data['doctor_id'] ?? null
        );

        if (! $ticket) {
            return ApiResponse::success(null, __('queues.empty'));
        }

        return ApiResponse::success(new QueueTicketResource($ticket), __('queues.called'));
    }

    public function start(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->start($ticket, $request->user());

        return ApiResponse::success(new QueueTicketResource($ticket), __('queues.started'));
    }

    public function complete(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->complete($ticket, $request->user());

        return ApiResponse::success(new QueueTicketResource($ticket), __('queues.completed'));
    }

    public function skip(SkipQueueRequest $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->skip($ticket, $request->string('reason')->toString());

        return ApiResponse::success(new QueueTicketResource($ticket), __('queues.skipped'));
    }

    public function noShow(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markNoShow($ticket);

        return ApiResponse::success(new QueueTicketResource($ticket), __('queues.no_show_marked'));
    }

    public function recall(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->recall($ticket);

        return ApiResponse::success(new QueueTicketResource($ticket), __('queues.recalled'));
    }
}
