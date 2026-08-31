<?php

declare(strict_types=1);

namespace Game\Alliance\Interface\Http;

use Game\Alliance\Application\AllianceStateService;
use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Interface\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AllianceController
{
    public function __construct(private AllianceStateService $alliances) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        return ApiResponse::success($this->alliances->handle($account));
    }
}
