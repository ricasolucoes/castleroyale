<?php

declare(strict_types=1);

namespace Game\Military\Interface\Http;

use Game\Identity\Domain\Account;
use Game\Military\Application\MilitaryStateService;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Interface\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class MilitaryController
{
    public function __construct(private MilitaryStateService $military) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        return ApiResponse::success($this->military->handle($account));
    }
}
