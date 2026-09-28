<?php

declare(strict_types=1);

namespace Tests\Unit\Gamification;

use Game\Gamification\Infrastructure\RicaGamesGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RicaGamesGatewayTest extends TestCase
{
    public function test_submits_score_to_ricagames_successfully(): void
    {
        config([
            'services.ricagames.url' => 'https://games.ricasolucoes.com.br/api/v1',
            'services.ricagames.game_code' => 'castleroyale',
            'services.ricagames.api_key' => 'test-api-key',
        ]);

        Http::fake([
            'https://games.ricasolucoes.com.br/api/v1/games/castleroyale/score' => Http::response([
                'status' => 'success',
                'message' => 'Score registered successfully',
                'data' => [
                    'score' => 1500,
                    'leaderboard_id' => 'ranking-poder',
                ],
            ], 200),
        ]);

        $gateway = new RicaGamesGateway;
        $result = $gateway->submitScore(
            leaderboardId: 'ranking-poder',
            playerName: 'Imperador',
            score: 1500,
            googleId: 'google-12345',
        );

        $this->assertSame('success', $result['status']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://games.ricasolucoes.com.br/api/v1/games/castleroyale/score'
                && $request['score'] === 1500
                && $request['google_id'] === 'google-12345'
                && $request['player_name'] === 'Imperador';
        });
    }

    public function test_handles_portal_downtime_gracefully(): void
    {
        config([
            'services.ricagames.url' => 'https://games.ricasolucoes.com.br/api/v1',
            'services.ricagames.game_code' => 'castleroyale',
        ]);

        Http::fake([
            'https://games.ricasolucoes.com.br/api/v1/games/castleroyale/score' => Http::response(null, 500),
        ]);

        $gateway = new RicaGamesGateway;
        $result = $gateway->submitScore(
            leaderboardId: 'ranking-poder',
            playerName: 'Imperador',
            score: 1500,
        );

        $this->assertSame('error', $result['status']);
    }

    public function test_fetches_leaderboards_from_portal(): void
    {
        config([
            'services.ricagames.url' => 'https://games.ricasolucoes.com.br/api/v1',
            'services.ricagames.game_code' => 'castleroyale',
        ]);

        Http::fake([
            'https://games.ricasolucoes.com.br/api/v1/games/castleroyale/leaderboards' => Http::response([
                'status' => 'success',
                'game' => 'Castle Royale',
                'leaderboards' => [
                    ['id' => 1, 'name' => 'Poder do Império'],
                ],
            ], 200),
        ]);

        $gateway = new RicaGamesGateway;
        $result = $gateway->getLeaderboards();

        $this->assertSame('success', $result['status']);
        $this->assertCount(1, $result['leaderboards'] ?? []);
    }
}
