import { QueryClient, QueryClientProvider, focusManager } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useMemo } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { ApiError } from '@/api/client';
import { useTheme } from '@/theme';

// Without this, TanStack Query's focus-based refetching never fires on React
// Native at all, and a player returning after minutes away would watch the
// resource bar free-run its extrapolation across a gap it cannot confirm.
// This is the documented RN recipe, and it benefits every query, not just
// city. `FocusManager` owns its own subscription lifecycle internally, so
// this runs once at module load rather than inside a component effect.
focusManager.setEventListener((handleFocus) => {
  const subscription = AppState.addEventListener('change', (status: AppStateStatus) => {
    handleFocus(status === 'active');
  });

  return () => subscription.remove();
});

/**
 * Root layout: the providers every screen depends on.
 *
 * TanStack Query owns all server state. Zustand (added per feature) owns only
 * ephemeral client state — see docs/mobile/architecture.md.
 */
export default function RootLayout() {
  const theme = useTheme();

  const queryClient = useMemo(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            // Retry only what the server said is worth retrying. Hammering a
            // PLAYER_PROTECTED or INSUFFICIENT_RESOURCES failure helps nobody.
            retry: (failureCount, error) => {
              if (error instanceof ApiError) {
                return error.retryable && failureCount < 3;
              }
              return failureCount < 2;
            },
          },
          mutations: { retry: false },
        },
      }),
    [],
  );

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <QueryClientProvider client={queryClient}>
          <StatusBar style={theme.name === 'dark' ? 'light' : 'dark'} />
          <Stack
            screenOptions={{
              headerShown: false,
              contentStyle: { backgroundColor: theme.color.bg.base },
            }}
          />
        </QueryClientProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
