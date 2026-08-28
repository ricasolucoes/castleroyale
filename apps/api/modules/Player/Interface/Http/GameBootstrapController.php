<?php

declare(strict_types=1);

namespace Game\Player\Interface\Http;

use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Application\Idempotency\IdempotencyService;
use Game\Shared\Interface\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final readonly class GameBootstrapController
{
    public function __construct(private GameBootstrapService $bootstrap, private IdempotencyService $idempotency) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        /** @var array{world_id?: string, name?: string} $input */
        $input = Validator::make($request->all(), [
            'world_id' => ['nullable', 'string', 'size:26'],
            'name' => ['nullable', 'string'],
        ])->validate();

        $worldId = isset($input['world_id']) && is_string($input['world_id']) ? $input['world_id'] : null;
        $name = array_key_exists('name', $input)
            ? (is_string($input['name']) ? $input['name'] : '')
            : null;

        return $this->idempotency->run($request, fn (): JsonResponse => ApiResponse::success(
            $this->bootstrap->handle($account, $worldId, $name),
            status: 201,
        ));
    }
}
