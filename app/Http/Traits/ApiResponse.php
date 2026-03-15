<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    protected function success($data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = ['success' => true];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message
     * @param array|object $errors
     * @param int $code
     * @return JsonResponse
     */
    protected function error(string $message, $errors = [], int $code = 422): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a paginated JSON response.
     *
     * @param LengthAwarePaginator $paginator
     * @param string|null $message
     * @return JsonResponse
     */
    protected function paginated(LengthAwarePaginator $paginator, ?string $message = null): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response);
    }

    /**
     * Return a not found JSON response.
     *
     * @param string $resource
     * @return JsonResponse
     */
    protected function notFound(string $resource = 'Resource'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "{$resource} not found.",
        ], 404);
    }

    /**
     * Return a created JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @return JsonResponse
     */
    protected function created($data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? 'Resource created successfully.', 201);
    }

    /**
     * Return an updated JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @return JsonResponse
     */
    protected function updated($data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? 'Resource updated successfully.');
    }

    /**
     * Return a deleted JSON response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    protected function deleted(?string $message = null): JsonResponse
    {
        return $this->success(null, $message ?? 'Resource deleted successfully.');
    }

    /**
     * Return an unauthorized JSON response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    protected function unauthorized(?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message ?? 'Unauthorized access.',
        ], 403);
    }

    /**
     * Return a validation error JSON response.
     *
     * @param array|object $errors
     * @param string|null $message
     * @return JsonResponse
     */
    protected function validationError($errors, ?string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? 'Validation failed.',
            $errors,
            422
        );
    }
}
