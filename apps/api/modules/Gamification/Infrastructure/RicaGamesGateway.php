<?php

declare(strict_types=1);

namespace Game\Gamification\Infrastructure;

use Game\Gamification\Domain\ExternalGamificationGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class RicaGamesGateway implements ExternalGamificationGateway
{
    private string $baseUrl;

    private string $gameCode;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ricagames.url', 'https://games.ricasolucoes.com.br/api/v1'), '/');
        $this->gameCode = (string) config('services.ricagames.game_code', 'castleroyale');
        $this->apiKey = config('services.ricagames.api_key');
    }

    /**
     * @return array{status: string, message?: string, data?: mixed}
     */
    public function submitScore(string $leaderboardId, string $playerName, float|int $score, ?string $googleId = null): array
    {
        try {
            $client = Http::timeout(5)->acceptJson();
            if ($this->apiKey !== null && $this->apiKey !== '') {
                $client = $client->withToken($this->apiKey);
            }

            $response = $client->post("{$this->baseUrl}/games/{$this->gameCode}/score", [
                'leaderboard_id' => $leaderboardId,
                'player_name' => $playerName,
                'score' => $score,
                'google_id' => $googleId,
            ]);

            if ($response->successful()) {
                $status = is_string($response->json('status')) ? $response->json('status') : 'success';
                $message = is_string($response->json('message')) ? $response->json('message') : null;
                $data = $response->json('data');

                $result = ['status' => $status];
                if ($message !== null) {
                    $result['message'] = $message;
                }
                if ($data !== null) {
                    $result['data'] = $data;
                }

                return $result;
            }

            Log::warning('Failed to submit score to RicaGames portal', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['status' => 'error', 'message' => 'Non-200 response from games portal'];
        } catch (Throwable $e) {
            Log::warning('Exception while connecting to RicaGames portal', [
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, game?: string, leaderboards?: list<array<string, mixed>>}
     */
    public function getLeaderboards(?string $gameSlug = null): array
    {
        $slug = $gameSlug ?? $this->gameCode;
        try {
            $response = Http::timeout(5)->acceptJson()->get("{$this->baseUrl}/games/{$slug}/leaderboards");
            if ($response->successful()) {
                $status = is_string($response->json('status')) ? $response->json('status') : 'success';
                $game = is_string($response->json('game')) ? $response->json('game') : null;
                $leaderboards = is_array($response->json('leaderboards'))
                    ? array_values(array_filter($response->json('leaderboards'), 'is_array'))
                    : [];

                $result = ['status' => $status, 'leaderboards' => $leaderboards];
                if ($game !== null) {
                    $result['game'] = $game;
                }

                return $result;
            }

            return ['status' => 'error', 'leaderboards' => []];
        } catch (Throwable) {
            return ['status' => 'error', 'leaderboards' => []];
        }
    }

    /**
     * @return array{status: string, data?: list<array<string, mixed>>}
     */
    public function getGames(): array
    {
        try {
            $response = Http::timeout(5)->acceptJson()->get("{$this->baseUrl}/games");
            if ($response->successful()) {
                $status = is_string($response->json('status')) ? $response->json('status') : 'success';
                $data = is_array($response->json('data'))
                    ? array_values(array_filter($response->json('data'), 'is_array'))
                    : [];

                return ['status' => $status, 'data' => $data];
            }

            return ['status' => 'error', 'data' => []];
        } catch (Throwable) {
            return ['status' => 'error', 'data' => []];
        }
    }
}
