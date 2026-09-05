import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ActivityIndicator, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { CityData, Construction } from '@castleroyale/contracts';
import type { ResourceKey } from '@castleroyale/tooling/design-tokens';

import { ApiError, apiRequest } from '@/api/client';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { ResourceCounter, formatResourceAmount } from '@/shared/components/ResourceCounter';
import { Text } from '@/shared/components/Text';
import { Timer } from '@/shared/components/Timer';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

const RESOURCE_KEYS: ResourceKey[] = ['food', 'wood', 'stone', 'iron', 'gold'];

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

function formatCost(cost: Partial<Record<ResourceKey, number>>, t: (key: string) => string): string {
  const parts = RESOURCE_KEYS
    .filter((resource) => (cost[resource] ?? 0) > 0)
    .map((resource) => `${t(`resources.${resource}`)} ${formatResourceAmount(cost[resource] ?? 0)}`);

  return parts.length > 0 ? parts.join(' · ') : t('building.no_cost');
}

export default function CityScreen() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();
  const styles = StyleSheet.create({
    center: {
      flex: 1,
      justifyContent: 'center',
      alignItems: 'center',
    },
    content: {
      flexGrow: 1,
      gap: theme.spacing.lg,
      paddingHorizontal: theme.spacing.lg,
      paddingBottom: theme.spacing['2xl'],
    },
    resourceGrid: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: theme.spacing.sm,
    },
    resourceItem: {
      flexGrow: 1,
      flexBasis: '28%',
      gap: theme.spacing.xs,
    },
    construction: {
      gap: theme.spacing.xs,
    },
    buildings: {
      gap: theme.spacing.md,
    },
    buildingHeader: {
      gap: theme.spacing.sm,
    },
    buildingTitle: {
      gap: theme.spacing.xs,
    },
  });
  const queryClient = useQueryClient();
  const cityQuery = useQuery({
    queryKey: ['game', 'city'],
    queryFn: () => apiRequest<CityData>('/game/city', {}, { authenticated: true }),
  });
  const upgrade = useMutation({
    mutationFn: (buildingCode: string) => apiRequest<{ construction: Construction }>(
      `/game/city/buildings/${encodeURIComponent(buildingCode)}/upgrade`,
      { method: 'POST' },
      { authenticated: true },
    ),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['game', 'city'] }),
  });

  if (cityQuery.isPending) {
    return (
      <View style={[styles.center, { backgroundColor: theme.color.bg.base }]}>
        <ActivityIndicator color={theme.color.accent.bronze} />
      </View>
    );
  }

  if (cityQuery.error || !cityQuery.data) {
    return (
      <View style={[styles.center, { backgroundColor: theme.color.bg.base }]}>
        <Text color={theme.color.danger}>{t(errorKey(cityQuery.error))}</Text>
        <Button title={t('common.retry')} variant="secondary" onPress={() => void cityQuery.refetch()} />
      </View>
    );
  }

  const city = cityQuery.data;
  const construction = city.construction;
  const buildings = city.slots.flatMap((slot) => (slot.building ? [slot.building] : []));
  const constructionTarget = construction
    ? Date.parse(construction.finishes_at) + (Date.now() - Date.parse(city.server_time))
    : null;

  return (
    <ScrollView
      contentContainerStyle={[
        styles.content,
        { backgroundColor: theme.color.bg.base, paddingTop: insets.top + theme.spacing.lg },
      ]}
    >
      <Text variant="display">{t(city.city.name_key)}</Text>
      <Text color={theme.color.text.secondary}>
        {t('city.location', { x: city.city.x, y: city.city.y })}
      </Text>

      <Card>
        <Text variant="heading">{t('city.resources')}</Text>
        <View style={styles.resourceGrid}>
          {RESOURCE_KEYS.map((resource) => (
            <View key={resource} style={styles.resourceItem}>
              <Text variant="caption" color={theme.color.text.secondary}>{t(`resources.${resource}`)}</Text>
              <ResourceCounter resource={resource} amount={city.resources.current[resource] ?? 0} />
              <Text variant="caption" color={theme.color.text.secondary}>
                {formatResourceAmount(city.resources.current[resource] ?? 0)} / {formatResourceAmount(city.resources.capacity[resource] ?? 0)}
              </Text>
            </View>
          ))}
        </View>
      </Card>

      <Card>
        <Text variant="heading">{t('city.construction')}</Text>
        {construction && constructionTarget !== null ? (
          <View style={styles.construction}>
            <Text>{t(`buildings.${construction.building_code}`)}</Text>
            <Text color={theme.color.text.secondary}>
              {t('building.level', { level: construction.target_level })}
            </Text>
            <Timer
              targetTimestamp={constructionTarget}
              onFinish={() => void cityQuery.refetch()}
            />
          </View>
        ) : (
          <Text color={theme.color.text.secondary}>{t('city.empty_construction')}</Text>
        )}
      </Card>

      <View style={styles.buildings}>
        <Text variant="heading">{t('city.buildings')}</Text>
        {buildings.map((building) => {
          const isBusy = construction?.building_code === building.code;
          const isMax = building.level >= building.max_level;

          return (
            <Card key={building.code}>
              <View style={styles.buildingHeader}>
                <View style={styles.buildingTitle}>
                  <Text variant="heading">{t(building.name_key)}</Text>
                  <Text color={theme.color.text.secondary}>
                    {t('building.level', { level: building.level })}
                  </Text>
                </View>
                <Text variant="caption" color={theme.color.text.secondary}>
                  {isMax ? t('building.max_level') : `${formatCost(building.next_level_cost, t)} · ${building.build_time_seconds} ${t('building.seconds')}`}
                </Text>
              </View>
              {!isMax && (
                <Button
                  title={isBusy ? t('city.construction') : t('building.upgrade')}
                  onPress={() => upgrade.mutate(building.code)}
                  disabled={isBusy || upgrade.isPending}
                />
              )}
            </Card>
          );
        })}
      </View>
      {upgrade.error && <Text color={theme.color.danger}>{t(errorKey(upgrade.error))}</Text>}
    </ScrollView>
  );
}
