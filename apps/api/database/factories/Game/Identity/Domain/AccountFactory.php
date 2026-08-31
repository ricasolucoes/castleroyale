<?php

declare(strict_types=1);

namespace Database\Factories\Game\Identity\Domain;

use Game\Identity\Domain\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
final class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'shield_expires_at' => null,
            'is_guest' => false,
            'provider' => null,
            'provider_id' => null,
        ];
    }
}
