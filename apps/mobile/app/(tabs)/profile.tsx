import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { GameBootstrap } from '@dominion/contracts';

import { ApiError, apiRequest } from '@/api/client';
import { clearTokens } from '@/features/auth/SecureStorage';
import { Badge } from '@/shared/components/Badge';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Skeleton } from '@/shared/components/Skeleton';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

export default function ProfileScreen() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();
  const [logoutError, setLogoutError] = useState<string | null>(null);
  const styles = StyleSheet.create({
    content: {
      flexGrow: 1,
      gap: theme.spacing.lg,
      paddingHorizontal: theme.spacing.lg,
      paddingBottom: theme.spacing['2xl'],
    },
    heading: { gap: theme.spacing.xs },
    identity: { gap: theme.spacing.md },
    identityHeader: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: theme.spacing.md,
    },
    identityCopy: { flex: 1, gap: theme.spacing.xs },
    loading: { gap: theme.spacing.md },
    error: { gap: theme.spacing.md },
  });
  const profileQuery = useQuery({
    queryKey: ['game', 'profile'],
    queryFn: () =>
      apiRequest<GameBootstrap>('/game/bootstrap', { method: 'POST' }, { authenticated: true }),
  });
  const logout = useMutation({
    mutationFn: () =>
      apiRequest<unknown>('/auth/logout', { method: 'POST' }, { authenticated: true }),
    onError: (error) => setLogoutError(errorKey(error)),
    onSettled: async () => {
      await clearTokens();
      router.replace('/auth/login');
    },
  });

  if (profileQuery.isPending) {
    return (
      <View
        style={[
          styles.loading,
          { flex: 1, backgroundColor: theme.color.bg.base, padding: insets.top + theme.spacing.lg },
        ]}
      >
        <Skeleton height={theme.spacing['2xl']} />
        <Skeleton height={theme.spacing['3xl'] * 2} />
        <Skeleton height={theme.spacing['3xl']} />
      </View>
    );
  }

  if (profileQuery.error || !profileQuery.data) {
    return (
      <View
        style={[
          styles.error,
          { flex: 1, backgroundColor: theme.color.bg.base, padding: insets.top + theme.spacing.lg },
        ]}
      >
        <Text color={theme.color.danger}>{t(errorKey(profileQuery.error))}</Text>
        <Button
          title={t('common.retry')}
          variant="secondary"
          onPress={() => void profileQuery.refetch()}
        />
      </View>
    );
  }

  const profile = profileQuery.data;

  return (
    <ScrollView
      contentContainerStyle={[
        styles.content,
        { backgroundColor: theme.color.bg.base, paddingTop: insets.top + theme.spacing.lg },
      ]}
    >
      <View style={styles.heading}>
        <Text variant="display">{t('profile.title')}</Text>
        <Text color={theme.color.text.secondary}>{t('profile.subtitle')}</Text>
      </View>

      <Card style={styles.identity}>
        <View style={styles.identityHeader}>
          <View style={styles.identityCopy}>
            <Text variant="heading">{profile.player.name}</Text>
            <Text variant="caption" color={theme.color.text.secondary}>
              {t('profile.player')}
            </Text>
          </View>
          <Badge label={t('profile.active')} variant="success" />
        </View>
      </Card>

      <Card style={styles.heading}>
        <Text variant="heading">{t('profile.world')}</Text>
        <Text>{profile.world.name}</Text>
        <Text variant="caption" color={theme.color.text.secondary}>
          {t('profile.world_code', { code: profile.world.code })}
        </Text>
      </Card>

      <Card style={styles.heading}>
        <Text variant="heading">{t('profile.city')}</Text>
        <Text>{t(profile.city.name_key)}</Text>
        <Text variant="caption" color={theme.color.text.secondary}>
          {t('world.coordinates', { x: profile.city.x, y: profile.city.y })}
        </Text>
      </Card>

      {logoutError && <Text color={theme.color.danger}>{t(logoutError)}</Text>}
      <Button
        title={logout.isPending ? t('profile.logging_out') : t('profile.logout')}
        variant="danger"
        onPress={() => {
          setLogoutError(null);
          logout.mutate();
        }}
        disabled={logout.isPending}
      />
      {logout.isPending && <ActivityIndicator color={theme.color.accent.bronze} />}
    </ScrollView>
  );
}
