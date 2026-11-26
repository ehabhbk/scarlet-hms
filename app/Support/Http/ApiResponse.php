<?php

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message ?? __('messages.ok'),
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message ?? __('messages.created'), 201);
    }

    public static function error(
        string $message,
        ?array $errors = null,
        int $status = 422,
        ?string $code = null
    ): JsonResponse {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'code' => $code ?? self::defaultCode($status),
            'errors' => $errors,
        ], static fn ($value) => $value !== null), $status);
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        mixed $collection = null,
        ?string $message = null
    ): JsonResponse {
        if ($collection instanceof AnonymousResourceCollection) {
            $response = $collection->response()->getData(true);
            $data = $response['data'];
        } elseif (is_array($collection)) {
            $data = $collection;
        } else {
            $data = $paginator->items();
        }

        return response()->json([
            'success' => true,
            'message' => $message ?? __('messages.ok'),
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'request_id' => request()->attributes->get('request_id'),
            ],
        ]);
    }

    protected static function defaultCode(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            422 => 'validation_failed',
            429 => 'too_many_requests',
            default => 'server_error',
        };
    }
}
