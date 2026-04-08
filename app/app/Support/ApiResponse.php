<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = [
            'data' => $data,
        ];

        $meta = self::filterMeta($meta);
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function paginated(
        array $data,
        int $page,
        int $perPage,
        int $total,
        array $meta = [],
        int $status = 200
    ): JsonResponse {
        $meta = array_merge($meta, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
        ]);

        return self::success($data, $meta, $status);
    }

    private static function filterMeta(array $meta): array
    {
        $filtered = [];

        foreach ($meta as $key => $value) {
            if ($value === null) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }
}
