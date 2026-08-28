<?php

declare(strict_types=1);

namespace Game\World\Interface\Http;

use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Interface\Http\ApiResponse;
use Game\World\Application\WorldViewportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final readonly class WorldViewportController
{
    public function __construct(private WorldViewportService $viewport) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        /** @var array{min_x: int, max_x: int, min_y: int, max_y: int} $input */
        $input = Validator::make($request->all(), [
            'min_x' => ['required', 'integer'],
            'max_x' => ['required', 'integer'],
            'min_y' => ['required', 'integer'],
            'max_y' => ['required', 'integer'],
        ])->validate();

        return ApiResponse::success($this->viewport->handle(
            $account,
            (int) $input['min_x'],
            (int) $input['max_x'],
            (int) $input['min_y'],
            (int) $input['max_y'],
        ));
    }
}
