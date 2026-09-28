import { useState } from 'react';
import { Image, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import type { CityData } from '@castleroyale/contracts';

import { computeSlotLayout } from '@/features/city/rendering/grid';
import { useCitySelectionStore } from '@/features/city/state/citySelectionStore';
import { CitySlot } from './CitySlot';
import { CitySlotDetailSheet } from './CitySlotDetailSheet';
import { ConstructionQueueStrip } from './ConstructionQueueStrip';
import { HudPanel } from '@/shared/components/HudPanel';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

/**
 * How many plots span the frame's width.
 *
 * Four, not "as many as fit": the plot is a picture of a building now, and the
 * eight ~48pt columns the old floor-division produced turned the city into a
 * sheet of icons. `computeSlotLayout` still owns the arithmetic and still drops
 * a column rather than ship an unhittable plot — this only sets the target.
 */
const PLOTS_ACROSS = 4;

export type CitySceneProps = {
  city: CityData;
  isRefreshing: boolean;
  onRefresh: () => void;
};

export function CityScene({ city, isRefreshing, onRefresh }: CitySceneProps) {
  const theme = useTheme();
  const { t } = useTranslation();
  const [frameWidth, setFrameWidth] = useState(0);
  const selectedSlot = useCitySelectionStore((state) => state.selectedSlot);
  const selectSlot = useCitySelectionStore((state) => state.selectSlot);
  const clearSelection = useCitySelectionStore((state) => state.clearSelection);

  const layout = computeSlotLayout(
    frameWidth,
    city.slots.length,
    Math.max(theme.spacing['3xl'], frameWidth / PLOTS_ACROSS),
    theme.minTouchTarget,
  );

  // The device clock is never trusted as the source of the deadline — same
  // skew correction the sheet and the pre-scene screen already applied.
  const clockSkewMs = Date.now() - Date.parse(city.server_time);

  const constructionByCode = new Map(
    city.constructions.map((construction) => [construction.building_code, construction]),
  );

  // One pass over the roster serves both queue-strip resolvers: a pip knows only
  // a building_code, but tapping it must open that building's plot.
  const plotByBuildingCode = new Map(
    city.slots
      .filter((slot) => slot.building !== null)
      .map((slot) => [
        slot.building!.code,
        { slot: slot.slot, nameKey: slot.building!.name_key },
      ]),
  );

  const selected = city.slots.find((s) => s.slot === selectedSlot) ?? null;
  const selectedConstruction =
    selected?.building ? (constructionByCode.get(selected.building.code) ?? null) : null;

  const styles = StyleSheet.create({
    root: { flex: 1, backgroundColor: theme.color.bg.base },
    scroll: { flex: 1 },
    // `flexGrow` so a city with few plots still paints ground to the bottom
    // edge instead of leaving the app background showing under the last row.
    scrollContent: { flexGrow: 1 },
    ground: {
      // `flexGrow`, never `flex: 1`. Inside a scroll container `flex: 1` pins
      // this to exactly the viewport height, and a wrapping row that overflows
      // a fixed height gets clipped instead of scrolling — the last rows of
      // plots would simply not exist. `flexGrow` with the default `auto` basis
      // sizes to the plots and still paints ground to the bottom edge when a
      // small city leaves space over.
      flexGrow: 1,
      flexDirection: 'row',
      flexWrap: 'wrap',
      alignContent: 'flex-start',
      // Room for the queue strip docked at the bottom, so the last row of
      // plots is never sitting underneath it.
      paddingBottom: theme.spacing['3xl'] * 2,
    },
    queueDock: {
      position: 'absolute',
      left: 0,
      right: 0,
      bottom: 0,
      paddingHorizontal: theme.spacing.sm,
      paddingBottom: theme.spacing.sm,
    },
  });

  return (
    <View style={styles.root}>
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={onRefresh}
            tintColor={theme.color.accent.bronze}
          />
        }
      >
        <View
          accessibilityLabel={t('city.scene_accessibility')}
          onLayout={(event) => setFrameWidth(event.nativeEvent.layout.width)}
          style={styles.ground}
        >
          {/* Full-bleed: the ground is the scene, not a texture behind a grid
              of opaque cards. */}
          <Image
            testID="city-ground"
            // eslint-disable-next-line @typescript-eslint/no-require-imports
            source={require('../../../../assets/city/city_ground.png')}
            resizeMode="cover"
            style={StyleSheet.absoluteFill}
          />
          {layout.tileSize > 0 &&
            city.slots.map((slot) => {
              const construction = slot.building
                ? (constructionByCode.get(slot.building.code) ?? null)
                : null;

              return (
                <CitySlot
                  key={slot.slot}
                  slot={slot}
                  size={layout.tileSize}
                  isBuilding={construction !== null}
                  isSelected={selectedSlot === slot.slot}
                  constructionFinishTimestamp={
                    construction ? Date.parse(construction.finishes_at) + clockSkewMs : null
                  }
                  onPress={selectSlot}
                />
              );
            })}
        </View>
      </ScrollView>

      {/* Identity at the edge, not a display heading eating the top third. */}
      <HudPanel corner="top-left">
        <Text variant="label" numberOfLines={1}>
          {t(city.city.name_key)}
        </Text>
        <Text variant="caption" color={theme.color.text.secondary}>
          {t('city.location', { x: city.city.x, y: city.city.y })}
        </Text>
      </HudPanel>

      <View style={styles.queueDock}>
        <ConstructionQueueStrip
          constructions={city.constructions}
          queueLimit={city.queue_limit}
          slotForBuildingCode={(code) => plotByBuildingCode.get(code)?.slot ?? null}
          nameKeyForBuildingCode={(code) => plotByBuildingCode.get(code)?.nameKey ?? null}
          serverTime={city.server_time}
          onSelectSlot={selectSlot}
        />
      </View>

      <CitySlotDetailSheet
        slot={selected}
        construction={selectedConstruction}
        serverTime={city.server_time}
        resources={city.resources.current}
        activeConstructions={city.constructions.length}
        queueLimit={city.queue_limit}
        open={selectedSlot !== null}
        onClose={clearSelection}
        onConstructionFinish={onRefresh}
      />
    </View>
  );
}
