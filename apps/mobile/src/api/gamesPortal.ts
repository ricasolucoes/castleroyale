/**
 * Client service for the central games portal: games.ricasolucoes.com.br
 */

import Constants from 'expo-constants';

const extra = (Constants.expoConfig?.extra ?? {}) as { gamesPortalUrl?: string };

export const GAMES_PORTAL_URL =
  process.env['EXPO_PUBLIC_GAMES_PORTAL_URL'] ??
  extra.gamesPortalUrl ??
  'https://games.ricasolucoes.com.br/api/v1';

export type PortalGame = {
  id: number;
  title: string;
  slug: string;
  is_active: boolean;
};

export type PortalLeaderboard = {
  id: number;
  game_id: number;
  title: string;
  sort_order: 'asc' | 'desc';
};

export async function fetchPortalGames(): Promise<PortalGame[]> {
  try {
    const res = await fetch(`${GAMES_PORTAL_URL}/games`, {
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) return [];
    const json = (await res.json()) as { status: string; data: PortalGame[] };
    return json.data ?? [];
  } catch {
    return [];
  }
}

export async function fetchPortalLeaderboards(gameSlug = 'castleroyale'): Promise<PortalLeaderboard[]> {
  try {
    const res = await fetch(`${GAMES_PORTAL_URL}/games/${gameSlug}/leaderboards`, {
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) return [];
    const json = (await res.json()) as { status: string; leaderboards: PortalLeaderboard[] };
    return json.leaderboards ?? [];
  } catch {
    return [];
  }
}
