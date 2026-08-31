<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Filament\Resources\DeviceSessionResource\Pages\ListDeviceSessions;
use App\Models\AdminAudit;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Game\Identity\Domain\Account;
use Game\Identity\Domain\DeviceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->staff()->create();
        $this->actingAs($this->user);
    }

    public function test_can_view_accounts(): void
    {
        $accounts = Account::factory()->count(3)->create();

        Livewire::test(ListAccounts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($accounts);
    }

    public function test_can_adjust_shield(): void
    {
        $account = Account::factory()->create(['shield_expires_at' => now()->addDay()]);
        $newExpiry = now()->addDays(2);

        Livewire::test(ListAccounts::class)
            ->callAction(
                TestAction::make('adjust_shield')->table($account),
                [
                    'expires_at' => $newExpiry->toDateTimeString(),
                    'reason' => 'Restore shield after support review.',
                ],
            );

        $this->assertSame($newExpiry->toDateTimeString(), $account->fresh()->shield_expires_at->toDateTimeString());
        $this->assertDatabaseHas('admin_audits', [
            'actor_user_id' => $this->user->id,
            'action' => 'account.adjust_shield',
            'target_id' => $account->id,
            'reason' => 'Restore shield after support review.',
        ]);
    }

    public function test_can_revoke_session(): void
    {
        $session = DeviceSession::factory()->create();

        Livewire::test(ListDeviceSessions::class)
            ->callAction(
                TestAction::make('revoke')->table($session),
                ['reason' => 'Terminate a compromised device session.'],
            );

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertDatabaseHas('admin_audits', [
            'actor_user_id' => $this->user->id,
            'action' => 'device_session.revoke',
            'target_id' => $session->id,
            'reason' => 'Terminate a compromised device session.',
        ]);
    }

    public function test_admin_audit_rejects_a_blank_reason(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\AdminAuditService::class)->record(
            'test.action',
            Account::class,
            'target',
            [],
            [],
            '   ',
        );

        $this->assertSame(0, AdminAudit::query()->count());
    }

    public function test_non_staff_cannot_reach_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.config('game.admin_path'));

        $response->assertForbidden();
        $response->assertDontSee('Filament');
    }
}
