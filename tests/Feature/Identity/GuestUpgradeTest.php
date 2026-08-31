<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_account_can_be_upgraded_and_preserves_id(): void
    {
        // Account factory might need to be imported or use full path
        // Assuming Account factory follows standard Laravel convention
        $guest = Account::factory()->create(['is_guest' => true]);
        $guestId = $guest->id;

        $this->actingAs($guest)
             ->postJson('/api/v1/auth/upgrade', [
                 'email' => 'new@example.com',
                 'password' => 'password',
             ], ['Idempotency-Key' => 'test-key'])
             ->assertOk();

        $guest->refresh();
        $this->assertFalse($guest->is_guest);
        $this->assertEquals($guestId, $guest->id);
    }
}
