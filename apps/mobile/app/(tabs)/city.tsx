import { useQuery } from '@tanstack/react-query';
import { ActivityIndicator, View } from 'react-native';
import type { CityData } from '@castleroyale/contracts';

import { ApiError, apiRequest } from '@/api/client';
import { Button } from '@/shared/components/Button';
import { Text } from '@/shared/components/Text';
import { CityScene } from '@/features/city/components/CityScene';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

export default function CityScreen() {
  const theme = useTheme();
  const { t } = useTranslation();
  const cityQuery = useQuery({
    queryKey: ['game', 'city'],
    queryFn: () => apiRequest<CityData>('/game/city', {}, { authenticated: true }),
  });

  if (cityQuery.isPending) {
    return (
      <View
        style={{
          flex: 1,
          justifyContent: 'center',
          alignItems: 'center',
          backgroundColor: theme.color.bg.base,
        }}
      >
        <ActivityIndicator color={theme.color.accent.bronze} />
      </View>
    );
  }

  if (cityQuery.error || !cityQuery.data) {
    return (
      <View
        style={{
          flex: 1,
          justifyContent: 'center',
          alignItems: 'center',
          backgroundColor: theme.color.bg.base,
          gap: theme.spacing.md,
        }}
      >
        <Text color={theme.color.danger}>{t(errorKey(cityQuery.error))}</Text>
        <Button variant="secondary" title={t('common.retry')} onPress={() => void cityQuery.refetch()} />
      </View>
    );
  }

  return (
    <CityScene
      city={cityQuery.data}
      isRefreshing={cityQuery.isRefetching}
      onRefresh={() => void cityQuery.refetch()}
    />
  );
}
