<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    /**
     * Tất cả endpoint trả JSON; lỗi dạng thống nhất (SRS 8.1).
     *
     * @param  array<string, mixed>  $data
     */
    protected function ok(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    /** @param  array<string, mixed>  $data */
    protected function accepted(array $data = []): JsonResponse
    {
        return response()->json($data, 202);
    }

    /** @param  array<string, mixed>  $meta */
    protected function collection(iterable $items, array $meta = []): JsonResponse
    {
        $payload = ['data' => iterator_to_array(is_iterable($items) ? $items : [$items])];
        foreach ($meta as $key => $value) {
            $payload[$key] = $value;
        }

        return response()->json($payload);
    }
}
