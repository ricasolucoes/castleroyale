<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Game configuration
|--------------------------------------------------------------------------
|
| The single place the product identity and the global gameplay constants
| live. Nothing in the codebase should hardcode the product name, and no
| balancing number should appear inline in a class — balancing data belongs
| in `packages/game-data` (see docs/game-design/ and ADR-013).
|
| The values here are *structural* limits and versioning knobs, not balance.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    |
    | `name` is the working title. Changing it here changes it everywhere:
    | API metadata, admin panel branding, mail, push payloads and the client
    | bootstrap document. No class or namespace embeds the product name.
    |
    */

    'name' => env('GAME_NAME', 'Castle Royale'),
    'code' => env('GAME_CODE', 'castleroyale'),
    'support_email' => env('GAME_SUPPORT_EMAIL', 'support@example.test'),
    'support_rate_limit_per_minute' => (int) env('SUPPORT_RATE_LIMIT_PER_MINUTE', 5),

    /*
    |--------------------------------------------------------------------------
    | Back office
    |--------------------------------------------------------------------------
    |
    | The admin panel path is configurable so it is not a guessable constant in
    | production. It is defence in depth, not the access control itself — that
    | is `User::canAccessPanel()`.
    |
    */

    'admin_path' => env('FILAMENT_PATH', 'admin'),

    /*
    | Bootstrap staff account created by StaffUserSeeder. Outside local the
    | seeder refuses to run unless a password is supplied explicitly.
    */

    'admin_seed' => [
        'email' => env('ADMIN_SEED_EMAIL', 'admin@example.test'),
        'password' => env('ADMIN_SEED_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content versioning
    |--------------------------------------------------------------------------
    |
    | Every balance-affecting dataset is versioned. Battles persist the combat
    | version they were simulated under so a rebalance never invalidates a
    | historical replay (ADR-015).
    |
    */

    'versions' => [
        'data' => (int) env('GAME_DATA_VERSION', 1),
        'combat' => (int) env('GAME_COMBAT_VERSION', 1),
        'economy' => (int) env('GAME_ECONOMY_VERSION', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Game data source
    |--------------------------------------------------------------------------
    |
    | Balancing datasets are authored as JSON in the shared monorepo package
    | and are read at runtime by `GameDataCatalog`, validated at import time by
    | `php artisan game:import-data`. The database import ADR-013 describes is
    | deferred per ADR-020: the JSON bundle is the runtime source of truth for
    | now, and becomes staged content behind a database import once a phase
    | needs content to ship without a deploy.
    |
    */

    'data_path' => env('GAME_DATA_PATH', base_path('../../packages/game-data')),
    'localization_path' => env('GAME_LOCALIZATION_PATH', base_path('../../packages/localization')),

    /*
    |--------------------------------------------------------------------------
    | Structural limits
    |--------------------------------------------------------------------------
    |
    | Hard ceilings that protect the server, not tuning values. A number here
    | bounds the worst case; balance lives in game-data.
    |
    */

    'limits' => [
        'max_cities_per_player' => 8,
        'max_concurrent_marches' => 12,
        'max_build_queue_slots' => 4,
        'max_alliance_members' => 100,
        'max_march_units' => 500_000,
        'max_chat_message_length' => 500,
        'world_viewport_max_tiles' => 4_096,
        'world_view_radius' => 8,
    ],

    'world' => [
        'default_code' => env('GAME_DEFAULT_WORLD', 'aurora'),
        'capacity' => (int) env('GAME_WORLD_CAPACITY', 1000),
        'generation_seed' => env('GAME_WORLD_GENERATION_SEED', 'aurora-v1'),
    ],

    'player' => [
        'name_min_length' => (int) env('PLAYER_NAME_MIN_LENGTH', 3),
        'name_max_length' => (int) env('PLAYER_NAME_MAX_LENGTH', 24),
        'denied_names' => array_values(array_filter(array_map(
            static fn (string $name): string => mb_strtolower(trim($name)),
            explode(',', (string) env('PLAYER_DENIED_NAMES', 'admin,administrator,moderator,system')),
        ))),
    ],

    'auth' => [
        'access_token_ttl_minutes' => (int) env('AUTH_ACCESS_TOKEN_TTL_MINUTES', 60),
        'refresh_token_ttl_days' => (int) env('AUTH_REFRESH_TOKEN_TTL_DAYS', 30),
        'max_device_sessions' => (int) env('AUTH_MAX_DEVICE_SESSIONS', 5),
        'rate_limit_per_minute' => (int) env('RATE_LIMIT_AUTH_PER_MINUTE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Time
    |--------------------------------------------------------------------------
    |
    | All persisted timestamps are UTC. `time_scale` accelerates every
    | server-side duration and exists only so developers do not wait four real
    | hours to test a completion screen. It is forced to 1 outside local.
    |
    */

    'time_scale' => env('APP_ENV') === 'local'
        ? max(1, (int) env('DEBUG_TIME_SCALE', 1))
        : 1,

    /*
    |--------------------------------------------------------------------------
    | Debug tooling
    |--------------------------------------------------------------------------
    |
    | Double-gated: the flag AND a non-production environment. See
    | docs/security/threat-model.md — an exposed debug menu is a full
    | economy compromise.
    |
    */

    'debug_menu' => [
        'enabled' => filter_var(env('DEBUG_MENU_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
            && in_array((string) env('APP_ENV', 'production'), ['local', 'testing', 'development'], true),
    ],

];
