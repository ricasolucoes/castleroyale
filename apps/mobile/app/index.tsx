import { Redirect } from 'expo-router';
import { useEffect, useState } from 'react';
import type { WorldList } from '@castleroyale/contracts';

import { ApiError, authenticatedRequest } from '@/api/client';
import { getTokens } from '@/features/auth/SecureStorage';

export default function Index() {
  const [destination, setDestination] = useState<'/auth/login' | '/onboarding' | '/(tabs)/city' | null>(null);

  useEffect(() => {
    void (async () => {
      const tokens = await getTokens();
      if (!tokens.access && !tokens.refresh) {
        setDestination('/auth/login');
        return;
      }

      try {
        const worlds = await authenticatedRequest<WorldList>('/game/worlds');
        setDestination(worlds.worlds.some((world) => world.has_player) ? '/(tabs)/city' : '/onboarding');
      } catch (error) {
        setDestination(error instanceof ApiError ? '/auth/login' : '/auth/login');
      }
    })();
  }, []);

  return destination === null ? null : <Redirect href={destination} />;
}
