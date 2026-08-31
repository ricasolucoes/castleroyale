<?php

declare(strict_types=1);

namespace Database\Factories\Game\Identity\Domain;

use Game\Identity\Domain\Account;
use Game\Identity\Domain\DeviceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceSession>
 */
final class DeviceSessionFactory extends Factory
{
    protected $model = DeviceSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'token_id' => null,
            'device_id' => fake()->uuid(),
            'device_name' => fake()->userAgent(),
            'platform' => 'ios',
            'ip' => fake()->ipv4(),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];
    }
}
