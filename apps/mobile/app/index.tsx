import { useQuery } from '@tanstack/react-query';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { apiRequest, ApiError } from '@/api/client';
import { useTheme } from '@/theme';

type HealthData = { status: string; checks: Record<string, boolean> };

/**
 * Connectivity check screen.
 *
 * A deliberate placeholder: it proves the client can reach the API and read the
 * standard envelope. The real entry point — splash, session restore, world
 * selection — is built in GSD Phases 02–04.
 */
export default function Index() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();

  const { data, error, isPending } = useQuery({
    queryKey: ['health'],
    queryFn: () => apiRequest<HealthData>('/health'),
  });

  const styles = StyleSheet.create({
    screen: {
      flex: 1,
      backgroundColor: theme.color.bg.base,
      paddingTop: insets.top + theme.spacing.xl,
      paddingHorizontal: theme.spacing.lg,
      gap: theme.spacing.md,
    },
    title: {
      fontSize: theme.typography.display.size,
      fontWeight: theme.typography.display.weight,
      color: theme.color.text.primary,
    },
    subtitle: {
      fontSize: theme.typography.body.size,
      color: theme.color.text.secondary,
    },
    card: {
      backgroundColor: theme.color.surface.raised,
      borderColor: theme.color.border.subtle,
      borderWidth: StyleSheet.hairlineWidth,
      borderRadius: theme.radius.lg,
      padding: theme.spacing.lg,
      gap: theme.spacing.sm,
    },
    label: {
      fontSize: theme.typography.label.size,
      color: theme.color.text.secondary,
    },
    ok: { color: theme.color.success, fontWeight: '600' },
    bad: { color: theme.color.danger, fontWeight: '600' },
  });

  return (
    <View style={styles.screen}>
      <Text style={styles.title}>Project Dominion</Text>
      <Text style={styles.subtitle}>Phase 00 — foundation</Text>

      <View style={styles.card}>
        <Text style={styles.label}>API connectivity</Text>

        {isPending ? (
          <ActivityIndicator color={theme.color.accent.bronze} />
        ) : error ? (
          <Text style={styles.bad}>
            {error instanceof ApiError ? error.code : 'Offline — cannot reach the API'}
          </Text>
        ) : (
          <Text style={styles.ok}>Connected — {data.status}</Text>
        )}
      </View>
    </View>
  );
}
