<?php

declare(strict_types=1);

namespace Game\Technology\Interface\Http;

use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Application\Idempotency\IdempotencyService;
use Game\Shared\Interface\Http\ApiResponse;
use Game\Technology\Application\ResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ResearchController
{
    public function __construct(
        private GameBootstrapService $bootstrap,
        private ResearchService $research,
        private IdempotencyService $idempotency,
    ) {}

    public function __invoke(Request $request, string $code): JsonResponse
    {
        return $this->idempotency->run($request, function () use ($request, $code): JsonResponse {
            $account = $request->user();
            if (! $account instanceof Account) {
                throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
            }
            $bootstrap = $this->bootstrap->handle($account);
            $key = (string) $request->header('Idempotency-Key');
            $research = $this->research->start(
                $account,
                $bootstrap['world']['id'],
                $bootstrap['city']['id'],
                $code,
                $key,
            );

            return ApiResponse::success(['research' => $research], status: 201);
        });
    }
}
