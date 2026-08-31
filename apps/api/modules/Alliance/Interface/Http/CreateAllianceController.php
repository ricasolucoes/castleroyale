<?php

declare(strict_types=1);

namespace Game\Alliance\Interface\Http;

use Game\Alliance\Application\AllianceStateService;
use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Application\Idempotency\IdempotencyService;
use Game\Shared\Interface\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final readonly class CreateAllianceController
{
    public function __construct(
        private AllianceStateService $alliances,
        private IdempotencyService $idempotency,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{name: string, tag: string} $input */
        $input = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:3', 'max:32'],
            'tag' => ['required', 'string', 'alpha_num', 'min:2', 'max:5'],
        ])->validate();

        return $this->idempotency->run($request, function () use ($request, $input): JsonResponse {
            $account = $request->user();
            if (! $account instanceof Account) {
                throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
            }

            return ApiResponse::success(
                $this->alliances->create($account, $input['name'], $input['tag']),
                status: 201,
            );
        });
    }
}
