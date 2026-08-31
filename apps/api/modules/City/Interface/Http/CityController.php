<?php

declare(strict_types=1);

namespace Game\City\Interface\Http;

use Game\City\Application\CityStateService;
use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Interface\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class CityController
{
    public function __construct(private CityStateService $city) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        return ApiResponse::success($this->city->handle($account));
    }

    public function show(Request $request, string $cityId): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        return ApiResponse::success($this->city->handle($account, $cityId));
    }
}
