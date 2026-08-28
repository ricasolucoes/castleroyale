import { useEffect, useState } from 'react';
import { router, Slot } from 'expo-router';
import type { AuthTokens } from '@dominion/contracts';

import { apiRequest } from '@/api/client';
import { clearTokens, getTokens, saveTokens } from '@/features/auth/SecureStorage';

export default function RootLayout() {
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    let active = true;

    async function restoreSession(): Promise<void> {
      try {
        const tokens = await getTokens();
        if (tokens.access) {
          await apiRequest('/auth/sessions', {}, { authenticated: true });
          router.replace('/(tabs)/city');
        } else if (tokens.refresh) {
          const refreshed = await apiRequest<AuthTokens>('/auth/refresh', {
            method: 'POST',
            body: JSON.stringify({ refresh_token: tokens.refresh }),
          });
          await saveTokens(refreshed.access_token, refreshed.refresh_token);
          router.replace('/(tabs)/city');
        } else {
          router.replace('/auth/login');
        }
      } catch {
        await clearTokens();
        router.replace('/auth/login');
      } finally {
        if (active) setIsReady(true);
      }
    }

    void restoreSession();
    return () => { active = false; };
  }, []);

  if (!isReady) return null;
  return <Slot />;
}
