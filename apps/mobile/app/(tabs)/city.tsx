import { ApiError } from '@/api/client';
import { Button } from '@/shared/components/Button';
import { SceneMessage } from '@/shared/components/SceneMessage';
import { CityScene } from '@/features/city/components/CityScene';
import { useCityQuery } from '@/features/city/api/useCityQuery';
import { useCityRealtime } from '@/features/city/realtime/useCityRealtime';
import { useTranslation } from '@/i18n/useTranslation';

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

export default function CityScreen() {
  const { t } = useTranslation();
  const cityQuery = useCityQuery();

  // Hook order must stay stable across renders, so this is called
  // unconditionally above the pending/error early returns below.
  useCityRealtime(cityQuery.data?.city.id ?? null, cityQuery.data?.realtime ?? null);

  if (cityQuery.isPending) {
    return <SceneMessage tone="working" title={t('city.loading')} />;
  }

  if (cityQuery.error || !cityQuery.data) {
    return (
      <SceneMessage
        tone="problem"
        title={t(errorKey(cityQuery.error))}
        action={
          <Button
            variant="secondary"
            title={t('common.retry')}
            onPress={() => void cityQuery.refetch()}
          />
        }
      />
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
