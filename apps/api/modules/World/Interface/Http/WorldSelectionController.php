<?php

declare(strict_types=1);

namespace Game\World\Interface\Http;

use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Application\Idempotency\IdempotencyService;
use Game\Shared\Interface\Http\ApiResponse;
use Game\World\Application\WorldSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final readonly class WorldSelectionController
{
    public function __construct(
        private WorldSelectionService $worlds,
        private GameBootstrapService $bootstrap,
        private IdempotencyService $idempotency,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        return ApiResponse::success($this->worlds->list($account));
    }

    public function select(Request $request, string $worldId): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        /** @var array{name?: string|null} $input */
        $input = Validator::make($request->all(), [
            'name' => ['nullable', 'string'],
        ])->validate();
        $name = array_key_exists('name', $input)
            ? (is_string($input['name']) ? $input['name'] : '')
            : null;

        return $this->idempotency->run($request, fn (): JsonResponse => ApiResponse::success(
            $this->bootstrap->handle($account, $worldId, $name),
            status: 201,
        ));
    }
}
