import { useState } from 'react';
import { View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import type { WorldData } from '@castleroyale/contracts';

import { ApiError, apiRequest } from '@/api/client';
import { MapCanvas } from '@/features/world/components/MapCanvas';
import { WorldArtGallery } from '@/features/world/components/WorldArtGallery';
import { useWorldViewport } from '@/features/world/data/useWorldViewport';
import { useCameraStore } from '@/features/world/state/cameraStore';
import { WorldTileDetailSheet } from '@/features/world/components/WorldTileDetailSheet';
import { Button } from '@/shared/components/Button';
import { HudPanel } from '@/shared/components/HudPanel';
import { SceneMessage } from '@/shared/components/SceneMessage';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

export default function WorldScreen() {
  const theme = useTheme();
  const { t } = useTranslation();
  const [showAtlas, setShowAtlas] = useState(false);
  const selectedX = useCameraStore((state) => state.selectedX);
  const selectedY = useCameraStore((state) => state.selectedY);
  const clearSelection = useCameraStore((state) => state.clearSelection);

  const worldQuery = useQuery({
    queryKey: ['game', 'world'],
    queryFn: () => apiRequest<WorldData>('/game/world', {}, { authenticated: true }),
  });

  const world = worldQuery.data;
  const playerCity = world?.cities.find((city) => city.is_player_city) ?? world?.cities[0];
  const radius = Math.max(world?.radius ?? 4, 4);
  const playerX = playerCity?.x ?? world?.center.x ?? 0;
  const playerY = playerCity?.y ?? world?.center.y ?? 0;
  const mapBounds = {
    minX: playerX - radius,
    maxX: playerX + radius,
    minY: playerY - radius,
    maxY: playerY + radius,
  };

  // Called unconditionally, above the early returns: hook order must not
  // depend on the world query's phase.
  const viewportQuery = useWorldViewport(mapBounds, world?.world.id);

  if (worldQuery.isPending) {
    return <SceneMessage tone="working" title={t('world.loading')} />;
  }

  if (worldQuery.error || !world) {
    return (
      <SceneMessage
        tone="problem"
        title={t(errorKey(worldQuery.error))}
        action={
          <Button
            title={t('common.retry')}
            variant="secondary"
            onPress={() => void worldQuery.refetch()}
          />
        }
      />
    );
  }

  const selectedCity =
    selectedX !== null && selectedY !== null
      ? (world.cities.find((city) => city.x === selectedX && city.y === selectedY) ?? null)
      : null;
  const selectedTile =
    selectedX !== null && selectedY !== null
      ? (viewportQuery.data?.viewport.tiles.find(
          (tile) => tile.x === selectedX && tile.y === selectedY,
        ) ?? null)
      : null;

  // Chebyshev distance: the world grid is square and a march moves a tile at a
  // time, so "four tiles away" means four steps, not a diagonal in metres.
  const distanceFromCity =
    selectedX !== null && selectedY !== null
      ? Math.max(Math.abs(selectedX - playerX), Math.abs(selectedY - playerY))
      : null;

  const isSyncing = viewportQuery.isPending || viewportQuery.isRefetching;
  const isStale = viewportQuery.data?.cacheStatus === 'stale';

  return (
    <View style={{ flex: 1, backgroundColor: theme.color.bg.sunken }}>
      {/* The map is the screen. Everything else floats over it. */}
      <MapCanvas
        tiles={viewportQuery.data?.viewport.tiles ?? []}
        bounds={mapBounds}
        playerX={playerX}
        playerY={playerY}
        cities={world.cities}
        selectedX={selectedX}
        selectedY={selectedY}
      />

      <WorldArtGallery open={showAtlas} onClose={() => setShowAtlas(false)} />

      <View
        style={{
          position: 'absolute',
          right: theme.spacing.lg,
          bottom: theme.spacing.lg + theme.minTouchTarget + theme.spacing.sm,
          zIndex: 10,
        }}
      >
        <Button
          title={`📖 ${t('world.art_open')}`}
          variant="secondary"
          onPress={() => setShowAtlas(true)}
        />
      </View>

      <HudPanel corner="top-left">
        <Text variant="label" numberOfLines={1}>
          {world.world.name}
        </Text>
        <Text variant="caption" color={theme.color.text.secondary}>
          {t('world.server_view', { radius: world.radius })}
        </Text>
        {(isSyncing || isStale) && (
          <Text
            variant="caption"
            color={isStale ? theme.color.warning : theme.color.text.secondary}
          >
            {isStale ? t('world.sync_stale') : t('world.sync_working')}
          </Text>
        )}
      </HudPanel>

      <HudPanel corner="top-right">
        <Text variant="caption" color={theme.color.text.secondary}>
          {t('world.your_city')}
        </Text>
        <Text variant="numeric">{t('world.coordinates', { x: playerX, y: playerY })}</Text>
      </HudPanel>

      {selectedX !== null && selectedY !== null && (
        <HudPanel corner="bottom-left">
          <Text variant="caption" color={theme.color.text.secondary}>
            {selectedCity ? t('world.selected_city') : t('world.selected_tile')}
          </Text>
          <Text variant="numeric">{t('world.coordinates', { x: selectedX, y: selectedY })}</Text>
          {selectedCity && (
            <Text variant="label" numberOfLines={1}>
              {t(selectedCity.name_key)}
            </Text>
          )}
          {distanceFromCity !== null && distanceFromCity > 0 && (
            <Text variant="caption" color={theme.color.text.secondary}>
              {t('world.distance_from_city', { tiles: distanceFromCity })}
            </Text>
          )}
        </HudPanel>
      )}

      <WorldTileDetailSheet
        tile={selectedTile}
        city={selectedCity}
        status={
          selectedTile
            ? 'ready'
            : isSyncing
              ? 'loading'
              : viewportQuery.isError
                ? 'error'
                : isStale
                  ? 'stale'
                  : 'empty'
        }
        open={selectedX !== null && selectedY !== null}
        onClose={clearSelection}
        onRetry={() => void viewportQuery.refetch()}
        onReset={() => {
          useCameraStore.getState().resetTo(playerX, playerY);
        }}
      />
    </View>
  );
}
