<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class ApiResponseService
{
    public function success(string $message = 'Thành công', mixed $data = null, int $statusCode = 200): JsonResponse
    {
        $body = [
            'status_code' => $statusCode,
            'message' => $message,
        ];

        if ($data !== null) {
            $body['data'] = $data;
        }

        return response()->json($body, $statusCode);
    }

    public function error(string $message, int $statusCode = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'status_code' => $statusCode,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function paginated(iterable $items, int $total, int $page, int $size): array
    {
        $totalPages = max((int) ceil($total / $size), 1);

        return [
            'content' => $items,
            'page' => $page,
            'size' => $size,
            'total_elements' => $total,
            'total_pages' => $totalPages,
            'first' => $page === 0,
            'last' => $page >= $totalPages - 1,
        ];
    }
}
