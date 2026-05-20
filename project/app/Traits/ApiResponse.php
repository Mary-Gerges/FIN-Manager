<?php
// app/Traits/ApiResponse.php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardized API response format
 * All controllers use this trait for consistent response structure
 */
trait ApiResponse
{
    /**
     * Success response
     *
     * @param  mixed  $data
     * @param  string $message
     * @param  int    $statusCode
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success'   => true,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => now()->toISOString(),
        ], $statusCode);
    }

    /**
     * Error response
     *
     * @param  string $message
     * @param  int    $statusCode
     * @param  mixed  $errors
     */
    protected function errorResponse(
        string $message = 'An error occurred',
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success'   => false,
            'message'   => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Paginated response — wraps Laravel paginator with standard envelope
     */
    protected function paginatedResponse(
        mixed $paginator,
        string $message = 'Data retrieved successfully'
    ): JsonResponse {
        return response()->json([
            'success'    => true,
            'message'    => $message,
            'data'       => $paginator->items(),
            'pagination' => [
                'current_page'  => $paginator->currentPage(),
                'per_page'      => $paginator->perPage(),
                'total'         => $paginator->total(),
                'last_page'     => $paginator->lastPage(),
                'from'          => $paginator->firstItem(),
                'to'            => $paginator->lastItem(),
                'has_more'      => $paginator->hasMorePages(),
            ],
            'timestamp'  => now()->toISOString(),
        ]);
    }

    /**
     * Validation error response (422)
     */
    protected function validationErrorResponse(array $errors): JsonResponse
    {
        return $this->errorResponse('Validation failed', 422, $errors);
    }

    /**
     * Not found response (404)
     */
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse("{$resource} not found", 404);
    }

    /**
     * Unauthorized response (401)
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Forbidden response (403)
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }
}
