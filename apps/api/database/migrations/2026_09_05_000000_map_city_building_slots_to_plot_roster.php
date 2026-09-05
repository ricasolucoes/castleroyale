<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Before the plot roster existed, `slot` was written as the building code.
 * Those rows would silently vanish from a roster-driven read, so map them onto
 * the plots `packages/game-data/data/starter.json` now assigns.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $legacy = [
        'palace' => 'plot_01',
        'farm' => 'plot_02',
        'lumber_mill' => 'plot_03',
        'quarry' => 'plot_04',
        'warehouse' => 'plot_05',
    ];

    public function up(): void
    {
        foreach ($this->legacy as $code => $slot) {
            DB::table('city_buildings')
                ->where('building_code', $code)
                ->where('slot', $code)
                ->update(['slot' => $slot]);
        }
    }

    public function down(): void
    {
        foreach ($this->legacy as $code => $slot) {
            DB::table('city_buildings')
                ->where('building_code', $code)
                ->where('slot', $slot)
                ->update(['slot' => $code]);
        }
    }
};
