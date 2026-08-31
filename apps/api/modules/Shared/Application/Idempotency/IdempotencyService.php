<?php

declare(strict_types=1);

namespace Game\Shared\Application\Idempotency;

use Closure;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Stores the result of mutating requests so mobile retries cannot repeat a
 * committed operation. The unique endpoint/key pair also serialises the first
 * request and makes a concurrent duplicate explicit to the caller.
 */
final readonly class IdempotencyService
{
    public function __construct(private Clock $clock) {}

    /**
     * @param Closure(): JsonResponse $operation
     */
    public function run(Request $request, Closure $operation): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '') {
            throw GameException::of(ErrorCode::IdempotencyKeyRequired, 'Idempotency-Key is required.');
        }

        $endpoint = strtoupper($request->method()).':'.$request->path();
        $payload = json_encode($request->all(), JSON_THROW_ON_ERROR);
        $payloadHash = hash('sha256', $payload);

        $actorKey = $this->actorKey($request);
        $record = $this->find($actorKey, $endpoint, $key);
        if ($record !== null) {
            return $this->replayOrReject($record, $payloadHash);
        }

        $recordId = Str::ulid()->toString();
        try {
            DB::table('idempotency_records')->insert([
                'id' => $recordId,
                'actor_key' => $actorKey,
                'endpoint' => $endpoint,
                'idempotency_key' => $key,
                'payload_hash' => $payloadHash,
                'status' => 'in_flight',
                'response_json' => null,
                'response_status' => null,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $record = $this->find($actorKey, $endpoint, $key);
            if ($record === null) {
                throw $exception;
            }

            return $this->replayOrReject($record, $payloadHash);
        }

        try {
            $response = $operation();
            DB::table('idempotency_records')->where('id', $recordId)->update([
                'status' => 'completed',
                'response_json' => json_encode($response->getData(true), JSON_THROW_ON_ERROR),
                'response_status' => $response->getStatusCode(),
                'updated_at' => $this->clock->now(),
            ]);

            return $response;
        } catch (Throwable $exception) {
            DB::table('idempotency_records')->where('id', $recordId)->delete();

            throw $exception;
        }
    }

    public function replayIfCompleted(Request $request): ?JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '') {
            return null;
        }

        $record = $this->find(
            $this->actorKey($request),
            strtoupper($request->method()).':'.$request->path(),
            $key,
        );
        if ($record === null) {
            return null;
        }

        $payload = json_encode($request->all(), JSON_THROW_ON_ERROR);

        return $this->replayOrReject($record, hash('sha256', $payload));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23000', '23505'], true);
    }

    /**
     * @return array{payload_hash: string, status: string, response_json: string|null, response_status: int|null}|null
     */
    private function find(string $actorKey, string $endpoint, string $key): ?array
    {
        $record = DB::table('idempotency_records')
            ->where('actor_key', $actorKey)
            ->where('endpoint', $endpoint)
            ->where('idempotency_key', $key)
            ->first();

        if ($record === null) {
            return null;
        }

        return [
            'payload_hash' => (string) $record->payload_hash,
            'status' => (string) $record->status,
            'response_json' => is_string($record->response_json) ? $record->response_json : null,
            'response_status' => $record->response_status === null ? null : (int) $record->response_status,
        ];
    }

    private function actorKey(Request $request): string
    {
        $bearerToken = $request->bearerToken();
        if ($bearerToken !== null) {
            $token = PersonalAccessToken::findToken($bearerToken);
            if ($token !== null) {
                return 'tokenable:'.$token->tokenable_type.':'.$token->tokenable_id;
            }

            return 'bearer:'.hash('sha256', $bearerToken);
        }

        return 'anonymous:'.$request->ip();
    }

    /**
     * @param array{payload_hash: string, status: string, response_json: string|null, response_status: int|null} $record
     */
    private function replayOrReject(array $record, string $payloadHash): JsonResponse
    {
        if ($record['payload_hash'] !== $payloadHash) {
            throw GameException::of(ErrorCode::IdempotencyKeyReused, 'Idempotency-Key was used for another payload.');
        }

        if ($record['status'] !== 'completed' || $record['response_json'] === null || $record['response_status'] === null) {
            throw GameException::of(ErrorCode::IdempotencyRequestInFlight, 'The original request is still running.');
        }

        $decoded = json_decode($record['response_json'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw GameException::of(ErrorCode::ServerError, 'Stored response is invalid.');
        }

        /** @var array<string, mixed> $decoded */
        return new JsonResponse($decoded, $record['response_status']);
    }
}
