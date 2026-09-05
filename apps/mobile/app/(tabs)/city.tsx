import { ActivityIndicator, View } from 'react-native';

import { ApiError } from '@/api/client';
import { Button } from '@/shared/components/Button';
import { Text } from '@/shared/components/Text';
import { CityScene } from '@/features/city/components/CityScene';
import { useCityQuery } from '@/features/city/api/useCityQuery';
import { useCityRealtime } from '@/features/city/realtime/useCityRealtime';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

export default function CityScreen() {
  const theme = useTheme();
  const { t } = useTranslation();
  const cityQuery = useCityQuery();

  // Hook order must stay stable across renders, so this is called
  // unconditionally above the pending/error early returns below.
  useCityRealtime(cityQuery.data?.city.id ?? null, cityQuery.data?.realtime ?? null);

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
