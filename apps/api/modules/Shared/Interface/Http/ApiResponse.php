<?php

declare(strict_types=1);

namespace Game\Shared\Interface\Http;

use Game\Shared\Application\Error\ErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * The one and only response envelope.
 *
 * Success: `{"data": ..., "meta": {...}}`
 * Failure: `{"error": {"code": ..., "message": ..., "details": {...}}}`
 *
 * Nothing else may reach the client. The shape is asserted by contract tests
 * against `packages/contracts/openapi.yaml`.
 */
final class ApiResponse
{
    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function success(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function error(
        ErrorCode $code,
        string $message,
        array $details = [],
        ?int $status = null,
    ): JsonResponse {
        $error = [
            'code' => $code->value,
            'message' => $message,
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        $error['retryable'] = $code->isRetryable();

        return new JsonResponse(['error' => $error], $status ?? $code->httpStatus());
    }

    /**
     * @param list<mixed> $items
     * @param array<string, mixed> $meta
     */
    public static function collection(array $items, array $meta = []): JsonResponse
    {
        return self::success($items, $meta);
    }
}
