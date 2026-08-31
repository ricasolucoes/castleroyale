<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Filament\Resources\AdminAuditResource\Pages\ListAdminAudits;
use App\Filament\Resources\AllianceResource\Pages\ListAlliances;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Filament\Resources\CityUnitResource\Pages\ListCityUnits;
use App\Filament\Resources\ConstructionOrderResource\Pages\ListConstructionOrders;
use App\Filament\Resources\EconomyLedgerResource\Pages\ListEconomyLedgers;
use App\Filament\Resources\PlayerResource\Pages\ListPlayers;
use App\Filament\Resources\WorldResource\Pages\ListWorlds;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Game\Alliance\Infrastructure\Alliance;
use Game\Alliance\Infrastructure\AllianceMember;
use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Identity\Domain\Account;
use Game\Military\Infrastructure\CityUnit;
use Game\Player\Infrastructure\Player;
use Game\World\Infrastructure\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->staff()->create();
        $this->actingAs($this->user);
    }

    public function test_can_view_and_rename_player(): void
    {
        $world = World::create([
            'code' => 'TEST_W1',
            'name' => 'Test World',
            'population' => 1,
            'capacity' => 1000,
            'spawn_index' => 1,
            'is_open' => true,
        ]);
        $account = Account::factory()->create();
        $player = Player::create([
            'world_id' => $world->id,
            'account_id' => $account->id,
            'name' => 'OldHero',
        ]);

        Livewire::test(ListPlayers::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$player])
            ->callAction(
                TestAction::make('rename_player')->table($player),
                [
                    'name' => 'NewHero',
                    'reason' => 'Offensive name violation correction.',
                ],
            );

        $this->assertSame('NewHero', $player->fresh()?->name);
        $this->assertDatabaseHas('admin_audits', [
            'actor_user_id' => $this->user->id,
            'action' => 'player.rename',
            'target_id' => $player->id,
            'reason' => 'Offensive name violation correction.',
        ]);
    }

    public function test_can_manage_world_status_and_capacity(): void
    {
        $world = World::create([
            'code' => 'W_AUDIT',
            'name' => 'Audit Realm',
            'population' => 50,
            'capacity' => 500,
            'spawn_index' => 1,
            'is_open' => true,
        ]);

        Livewire::test(ListWorlds::class)
            ->assertSuccessful()
            ->callAction(
                TestAction::make('toggle_open')->table($world),
                [
                    'is_open' => false,
                    'reason' => 'Emergency maintenance close.',
                ],
            );

        $this->assertFalse($world->fresh()?->is_open);
        $this->assertDatabaseHas('admin_audits', [
            'actor_user_id' => $this->user->id,
            'action' => 'world.toggle_open',
            'target_id' => $world->id,
        ]);

        Livewire::test(ListWorlds::class)
            ->callAction(
                TestAction::make('adjust_capacity')->table($world),
                [
                    'capacity' => 2000,
                    'reason' => 'Server scale-up.',
                ],
            );

        $this->assertSame(2000, $world->fresh()?->capacity);
    }

    public function test_can_grant_resources_and_teleport_city(): void
    {
        $world = World::create([
            'code' => 'W_CITY',
            'name' => 'City World',
            'population' => 1,
            'capacity' => 1000,
            'spawn_index' => 1,
            'is_open' => true,
        ]);
        $account = Account::factory()->create();
        $player = Player::create([
            'world_id' => $world->id,
            'account_id' => $account->id,
            'name' => 'CityOwner',
        ]);
        $city = City::create([
            'world_id' => $world->id,
            'player_id' => $player->id,
            'name_key' => 'city_capital',
            'x' => 10,
            'y' => 20,
            'food' => 500,
            'wood' => 500,
            'stone' => 500,
            'iron' => 500,
            'gold' => 100,
            'food_capacity' => 10000,
            'wood_capacity' => 10000,
            'stone_capacity' => 10000,
            'iron_capacity' => 10000,
            'gold_capacity' => 5000,
            'last_accrued_at' => now(),
        ]);

        Livewire::test(ListCities::class)
            ->assertSuccessful()
            ->callAction(
                TestAction::make('grant_resources')->table($city),
                [
                    'food' => 1000,
                    'gold' => 500,
                    'reason' => 'Event reward compensation.',
                ],
            );

        $freshCity = $city->fresh();
        $this->assertSame(1500, $freshCity?->food);
        $this->assertSame(600, $freshCity?->gold);

        $this->assertDatabaseHas('economy_ledger', [
            'city_id' => $city->id,
            'resource' => 'food',
            'amount' => 1000,
            'reason' => 'gm_grant',
        ]);
        $this->assertDatabaseHas('admin_audits', [
            'action' => 'city.grant_resources',
            'target_id' => $city->id,
        ]);

        Livewire::test(ListCities::class)
            ->callAction(
                TestAction::make('teleport')->table($city),
                [
                    'x' => 42,
                    'y' => 84,
                    'reason' => 'Player stuck in obstacle.',
                ],
            );

        $this->assertSame(42, $city->fresh()?->x);
        $this->assertSame(84, $city->fresh()?->y);
    }

    public function test_can_complete_construction_order_instantly(): void
    {
        $world = World::create([
            'code' => 'W_ORD',
            'name' => 'Order World',
            'population' => 1,
            'capacity' => 1000,
            'spawn_index' => 1,
            'is_open' => true,
        ]);
        $account = Account::factory()->create();
        $player = Player::create([
            'world_id' => $world->id,
            'account_id' => $account->id,
            'name' => 'Builder',
        ]);
        $city = City::create([
            'world_id' => $world->id,
            'player_id' => $player->id,
            'name_key' => 'city_capital',
            'x' => 5,
            'y' => 5,
            'food' => 100,
            'wood' => 100,
            'stone' => 100,
            'iron' => 100,
            'gold' => 100,
            'food_capacity' => 1000,
            'wood_capacity' => 1000,
            'stone_capacity' => 1000,
            'iron_capacity' => 1000,
            'gold_capacity' => 1000,
            'last_accrued_at' => now(),
        ]);
        $building = CityBuilding::create([
            'world_id' => $world->id,
            'city_id' => $city->id,
            'slot' => 'center',
            'building_code' => 'headquarters',
            'level' => 1,
        ]);
        $order = ConstructionOrder::create([
            'world_id' => $world->id,
            'city_id' => $city->id,
            'building_code' => 'headquarters',
            'from_level' => 1,
            'target_level' => 2,
            'idempotency_key' => (string) \Illuminate\Support\Str::ulid(),
            'started_at' => now(),
            'finishes_at' => now()->addMinutes(10),
            'completed_at' => null,
        ]);

        Livewire::test(ListConstructionOrders::class)
            ->assertSuccessful()
            ->callAction(
                TestAction::make('complete_now')->table($order),
                [
                    'reason' => 'Fix stuck timer queue.',
                ],
            );

        $this->assertNotNull($order->fresh()?->completed_at);
        $this->assertSame(2, $building->fresh()?->level);
        $this->assertDatabaseHas('admin_audits', [
            'action' => 'construction.complete_now',
            'target_id' => $order->id,
        ]);
    }

    public function test_can_adjust_city_garrison_units(): void
    {
        $world = World::create([
            'code' => 'W_MIL',
            'name' => 'Military World',
            'population' => 1,
            'capacity' => 1000,
            'spawn_index' => 1,
            'is_open' => true,
        ]);
        $account = Account::factory()->create();
        $player = Player::create([
            'world_id' => $world->id,
            'account_id' => $account->id,
            'name' => 'Warlord',
        ]);
        $city = City::create([
            'world_id' => $world->id,
            'player_id' => $player->id,
            'name_key' => 'city_capital',
            'x' => 1,
            'y' => 1,
            'food' => 100,
            'wood' => 100,
            'stone' => 100,
            'iron' => 100,
            'gold' => 100,
            'food_capacity' => 1000,
            'wood_capacity' => 1000,
            'stone_capacity' => 1000,
            'iron_capacity' => 1000,
            'gold_capacity' => 1000,
            'last_accrued_at' => now(),
        ]);
        $unit = CityUnit::create([
            'world_id' => $world->id,
            'city_id' => $city->id,
            'unit_code' => 'swordsman',
            'quantity' => 50,
        ]);

        Livewire::test(ListCityUnits::class)
            ->assertSuccessful()
            ->callAction(
                TestAction::make('adjust_quantity')->table($unit),
                [
                    'quantity' => 100,
                    'reason' => 'Restore troops lost to glitch.',
                ],
            );

        $this->assertSame(100, $unit->fresh()?->quantity);
        $this->assertDatabaseHas('admin_audits', [
            'action' => 'military.adjust_garrison',
            'target_id' => $unit->id,
        ]);
    }

    public function test_can_disband_alliance(): void
    {
        $world = World::create([
            'code' => 'W_ALL',
            'name' => 'Alliance World',
            'population' => 1,
            'capacity' => 1000,
            'spawn_index' => 1,
            'is_open' => true,
        ]);
        $account = Account::factory()->create();
        $player = Player::create([
            'world_id' => $world->id,
            'account_id' => $account->id,
            'name' => 'Leader',
        ]);
        $alliance = Alliance::create([
            'world_id' => $world->id,
            'leader_player_id' => $player->id,
            'name' => 'Legion of Honor',
            'tag' => 'LOH',
            'member_count' => 1,
            'max_members' => 50,
        ]);
        $member = AllianceMember::create([
            'world_id' => $world->id,
            'alliance_id' => $alliance->id,
            'player_id' => $player->id,
            'role' => 'leader',
        ]);

        Livewire::test(ListAlliances::class)
            ->assertSuccessful()
            ->callAction(
                TestAction::make('disband')->table($alliance),
                [
                    'reason' => 'Inappropriate alliance name and hate speech.',
                ],
            );

        $this->assertDatabaseMissing('alliances', ['id' => $alliance->id]);
        $this->assertDatabaseMissing('alliance_members', ['id' => $member->id]);
        $this->assertDatabaseHas('admin_audits', [
            'action' => 'alliance.disband',
            'target_id' => $alliance->id,
        ]);
    }

    public function test_can_view_economy_ledgers_and_admin_audits(): void
    {
        Livewire::test(ListEconomyLedgers::class)->assertSuccessful();
        Livewire::test(ListAdminAudits::class)->assertSuccessful();
    }
}
