<?php

namespace App\Support;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiResponse
{
    /** @param array<string, mixed> $meta */
    public static function success(Request $request, mixed $data = null, string $message = 'Thành công.', int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [...$meta, 'request_id' => RequestIdentity::get($request)],
        ], $status)->header('X-Request-ID', RequestIdentity::get($request));
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string|list<string>>  $headers
     */
    public static function error(Request $request, ErrorCode $code, array $errors = [], ?int $status = null, array $headers = []): JsonResponse
    {
        $status ??= $code->httpStatus();

        return response()->json([
            'success' => false,
            'code' => $code->value,
            'message' => $code->message(),
            'errors' => (object) ($status >= 500 ? [] : $errors),
            'meta' => ['request_id' => RequestIdentity::get($request)],
        ], $status, $headers)->header('X-Request-ID', RequestIdentity::get($request));
    }
}
