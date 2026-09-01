import { ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useQuery } from '@tanstack/react-query';
import type { WorldData } from '@castleroyale/contracts';

import { ApiError, apiRequest } from '@/api/client';
import { MapCanvas } from '@/features/world/components/MapCanvas';
import { useWorldViewport } from '@/features/world/data/useWorldViewport';
import { useCameraStore } from '@/features/world/state/cameraStore';
import { WorldTileDetailSheet } from '@/features/world/components/WorldTileDetailSheet';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Skeleton } from '@/shared/components/Skeleton';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

export default function WorldScreen() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();
  const selectedX = useCameraStore((state) => state.selectedX);
  const selectedY = useCameraStore((state) => state.selectedY);
  const clearSelection = useCameraStore((state) => state.clearSelection);
  const styles = StyleSheet.create({
    content: {
      flexGrow: 1,
      gap: theme.spacing.lg,
      paddingHorizontal: theme.spacing.lg,
      paddingBottom: theme.spacing['2xl'],
    },
    heading: { gap: theme.spacing.xs },
    cityList: { gap: theme.spacing.sm },
    cityRow: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: theme.spacing.md,
    },
    cityCopy: { flex: 1, gap: theme.spacing.xs },
    selected: { gap: theme.spacing.xs },
    loading: { gap: theme.spacing.md },
  });
  const worldQuery = useQuery({
    queryKey: ['game', 'world'],
    queryFn: () => apiRequest<WorldData>('/game/world', {}, { authenticated: true }),
  });
  const sourceWorld = worldQuery.data;
  const sourceCity = sourceWorld?.cities.find((city) => city.is_player_city) ?? sourceWorld?.cities[0];
  const sourceRadius = Math.max(sourceWorld?.radius ?? 4, 4);
  const sourceX = sourceCity?.x ?? sourceWorld?.center.x ?? 0;
  const sourceY = sourceCity?.y ?? sourceWorld?.center.y ?? 0;
  const viewportBounds = {
    minX: sourceX - sourceRadius,
    maxX: sourceX + sourceRadius,
    minY: sourceY - sourceRadius,
    maxY: sourceY + sourceRadius,
  };
  const viewportQuery = useWorldViewport(viewportBounds, sourceWorld?.world.id);

  if (worldQuery.isPending) {
    return (
      <View
        style={[
          styles.loading,
          { flex: 1, backgroundColor: theme.color.bg.base, padding: insets.top + theme.spacing.lg },
        ]}
      >
        <Skeleton height={theme.spacing['2xl']} />
        <Skeleton height={theme.spacing.lg} />
        <Skeleton height={theme.spacing['3xl'] * 4} />
      </View>
    );
  }

  if (worldQuery.error || !worldQuery.data) {
    return (
      <View
        style={[
          styles.loading,
          { flex: 1, backgroundColor: theme.color.bg.base, padding: insets.top + theme.spacing.lg },
        ]}
      >
        <Text color={theme.color.danger}>{t(errorKey(worldQuery.error))}</Text>
        <Button
          title={t('common.retry')}
          variant="secondary"
          onPress={() => void worldQuery.refetch()}
        />
      </View>
    );
  }

  const world = worldQuery.data;
  const playerCity = world.cities.find((city) => city.is_player_city) ?? world.cities[0];
  const radius = Math.max(world.radius, 4);
  const playerX = playerCity?.x ?? world.center.x;
  const playerY = playerCity?.y ?? world.center.y;
  const mapBounds = {
    minX: playerX - radius,
    maxX: playerX + radius,
    minY: playerY - radius,
    maxY: playerY + radius,
  };

  const selectedCity = selectedX !== null && selectedY !== null 
    ? world.cities.find((city) => city.x === selectedX && city.y === selectedY) ?? null
    : null;
  const selectedTile = selectedX !== null && selectedY !== null 
    ? viewportQuery.data?.viewport.tiles.find((tile) => tile.x === selectedX && tile.y === selectedY) ?? null
    : null;

  return (
    <ScrollView
      contentContainerStyle={[
        styles.content,
        { backgroundColor: theme.color.bg.base, paddingTop: insets.top + theme.spacing.lg },
      ]}
    >
      <View style={styles.heading}>
        <Text variant="display">{t('world.title')}</Text>
        <Text color={theme.color.text.secondary}>{t('world.subtitle')}</Text>
        <Text variant="caption" color={theme.color.text.secondary}>
          {t('world.server_view', { radius: world.radius })}
        </Text>
      </View>

      <Card>
        <View style={styles.heading}>
          <Text variant="heading">
            {t('world.title')} · {world.world.name}
          </Text>
          <Text variant="caption" color={theme.color.text.secondary}>
            {t('world.map_hint')}
          </Text>
        </View>
        <MapCanvas
          tiles={viewportQuery.data?.viewport.tiles ?? []}
          bounds={mapBounds}
          playerX={playerX}
          playerY={playerY}
          cities={world.cities}
        />
        {selectedCity && (
          <View style={[styles.selected, { marginTop: theme.spacing.md }]}>
            <Text variant="label">{t('world.selected_city')}</Text>
            <Text>{t(selectedCity.name_key)}</Text>
            <Text variant="caption" color={theme.color.text.secondary}>
              {t('world.coordinates', { x: selectedCity.x, y: selectedCity.y })}
            </Text>
          </View>
        )}
      </Card>



      <Button
        title={t('world.refresh')}
        variant="secondary"
        onPress={() => void worldQuery.refetch()}
      />
      <WorldTileDetailSheet
        tile={selectedTile}
        status={selectedTile ? 'ready' : (viewportQuery.isPending || viewportQuery.isRefetching) ? 'loading' : viewportQuery.isError ? 'error' : viewportQuery.data?.cacheStatus === 'stale' ? 'stale' : 'empty'}
        open={selectedX !== null && selectedY !== null}
        onClose={clearSelection}
        onRetry={() => void viewportQuery.refetch()}
        onReset={() => {
          useCameraStore.getState().resetTo(playerX, playerY);
        }}
      />
    </ScrollView>
  );
}
