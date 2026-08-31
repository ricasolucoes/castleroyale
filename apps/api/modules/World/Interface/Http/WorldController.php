<?php

declare(strict_types=1);

namespace Game\World\Interface\Http;

use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Interface\Http\ApiResponse;
use Game\World\Application\WorldStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class WorldController
{
    public function __construct(private WorldStateService $world) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        return ApiResponse::success($this->world->handle($account));
    }
}
