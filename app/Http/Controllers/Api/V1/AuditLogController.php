<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user:id,name');

        if ($event = $request->string('event')->toString()) {
            $query->where('event', 'like', $event.'%');
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($type = $request->string('auditable_type')->toString()) {
            $query->where('auditable_type', $type);
        }

        if ($search = $request->string('search')->toString()) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('description', 'like', $like)
                    ->orWhere('event', 'like', $like)
                    ->orWhere('request_id', 'like', $like);
            });
        }

        if ($from = $request->string('from')->toString()) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        if ($to = $request->string('to')->toString()) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $logs = $query
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 30), 5), 100))
            ->withQueryString();

        return ApiResponse::paginated($logs, AuditLogResource::collection($logs));
    }
}
