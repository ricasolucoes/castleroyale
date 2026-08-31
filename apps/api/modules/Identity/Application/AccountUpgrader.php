<?php

declare(strict_types=1);

namespace Game\Identity\Application;

use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class AccountUpgrader
{
    public function upgrade(string $accountId, ?string $email, ?string $password, ?string $provider, ?string $providerId): void
    {
        DB::transaction(function () use ($accountId, $email, $password, $provider, $providerId): void {
            $query = Account::query()->whereKey($accountId);
            $query->getQuery()->lockForUpdate();
            $account = $query->firstOrFail();

            if ($account->is_guest !== true) {
                throw GameException::of(ErrorCode::Conflict, 'This account is already upgraded.');
            }

            if ($email !== null) {
                if ($password === null) {
                    throw GameException::of(ErrorCode::ValidationFailed, 'An email upgrade requires a password.');
                }

                $account->email = $email;
                $account->password = Hash::make($password);
            } elseif ($provider !== null && $providerId !== null) {
                $account->provider = $provider;
                $account->provider_id = $providerId;
            } else {
                throw GameException::of(ErrorCode::ValidationFailed, 'An upgrade requires credentials.');
            }

            $account->is_guest = false;
            $account->save();
        });
    }
}
