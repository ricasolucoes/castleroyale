import { GAMES_PORTAL_URL, fetchPortalGames, fetchPortalLeaderboards } from '../src/api/gamesPortal';

describe('Central Games Portal Client (games.ricasolucoes.com.br)', () => {
  it('points to games.ricasolucoes.com.br by default', () => {
    expect(GAMES_PORTAL_URL).toContain('games.ricasolucoes.com.br');
  });

  it('handles games fetch gracefully when network fails', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = jest.fn().mockRejectedValue(new Error('Network error'));

    try {
      const games = await fetchPortalGames();
      expect(games).toEqual([]);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it('fetches leaderboards from portal', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        status: 'success',
        game: 'Castle Royale',
        leaderboards: [{ id: 1, game_id: 1, title: 'Poder Total do Império', sort_order: 'desc' }],
      }),
    } as Response);

    try {
      const leaderboards = await fetchPortalLeaderboards('castleroyale');
      expect(leaderboards).toHaveLength(1);
      expect(leaderboards[0]?.title).toBe('Poder Total do Império');
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
