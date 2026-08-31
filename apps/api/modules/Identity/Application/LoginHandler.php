<?php

declare(strict_types=1);

namespace Game\Identity\Application;

use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Illuminate\Support\Facades\Hash;

final readonly class LoginHandler
{
    public function __construct(private TokenIssuer $tokens) {}

    /**
     * @param array{device_id?: string, device_name?: string, platform?: string, ip?: string} $device
     * @return array{access_token: string, refresh_token: string}
     */
    public function handle(string $email, string $password, array $device = []): array
    {
        $account = Account::query()->where('email', $email)->first();

        if ($account === null || $account->password === null || ! Hash::check($password, $account->password)) {
            throw GameException::of(ErrorCode::InvalidCredentials, 'Invalid credentials.');
        }

        return $this->tokens->issue($account, $device);
    }
}
