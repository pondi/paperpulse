<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    /**
     * Success response
     */
    protected function success($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $status);
    }

    /**
     * Error response
     */
    protected function error(string $message = 'Error', int $status = 400, $errors = null, ?string $code = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => $code ?? self::defaultCodeForStatus($status),
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString(),
        ], $status);
    }

    /**
     * Validation error response
     */
    protected function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, 422, $errors, 'VALIDATION_ERROR');
    }

    /**
     * Not found response
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404, code: 'NOT_FOUND');
    }

    /**
     * Unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401, code: 'UNAUTHORIZED');
    }

    /**
     * Forbidden response
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403, code: 'FORBIDDEN');
    }

    private static function defaultCodeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'DUPLICATE_FILE',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            default => 'INTERNAL_ERROR',
        };
    }

    /**
     * Paginated response
     */
    protected function paginated($data, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
