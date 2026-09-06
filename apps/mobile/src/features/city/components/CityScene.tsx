import { useState } from 'react';
import { RefreshControl, ScrollView, View } from 'react-native';
import type { CityData } from '@castleroyale/contracts';

import { computeSlotLayout } from '@/features/city/rendering/grid';
import { useCitySelectionStore } from '@/features/city/state/citySelectionStore';
import { CitySlot } from './CitySlot';
import { CitySlotDetailSheet } from './CitySlotDetailSheet';
import { ConstructionQueueStrip } from './ConstructionQueueStrip';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

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
    theme.spacing['3xl'],
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

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: theme.color.bg.base }}
      contentContainerStyle={{
        flexGrow: 1,
        paddingHorizontal: theme.spacing.sm,
        // The resource bar above the tab navigator owns the top safe area
        // now; re-adding the top inset here would count the notch twice.
        paddingTop: theme.spacing.sm,
        paddingBottom: theme.spacing.sm,
        gap: theme.spacing.sm,
      }}
      refreshControl={
        <RefreshControl
          refreshing={isRefreshing}
          onRefresh={onRefresh}
          tintColor={theme.color.accent.bronze}
        />
      }
    >
      <View style={{ gap: theme.spacing.xs }}>
        <Text variant="display">{t(city.city.name_key)}</Text>
        <Text color={theme.color.text.secondary}>
          {t('city.location', { x: city.city.x, y: city.city.y })}
        </Text>
      </View>

      <ConstructionQueueStrip
        constructions={city.constructions}
        queueLimit={city.queue_limit}
        slotForBuildingCode={(code) => plotByBuildingCode.get(code)?.slot ?? null}
        nameKeyForBuildingCode={(code) => plotByBuildingCode.get(code)?.nameKey ?? null}
        serverTime={city.server_time}
        onSelectSlot={selectSlot}
      />

      <View
        accessibilityLabel={t('city.scene_accessibility')}
        onLayout={(event) => setFrameWidth(event.nativeEvent.layout.width)}
        style={{
          flex: 1,
          flexDirection: 'row',
          flexWrap: 'wrap',
          backgroundColor: theme.color.bg.sunken,
          borderRadius: theme.radius.lg,
        }}
      >
        {layout.tileSize > 0 &&
          city.slots.map((slot) => {
            const construction = slot.building ? (constructionByCode.get(slot.building.code) ?? null) : null;
            const isBuilding = construction !== null;

            return (
              <CitySlot
                key={slot.slot}
                slot={slot}
                size={layout.tileSize}
                isBuilding={isBuilding}
                constructionFinishTimestamp={
                  construction ? Date.parse(construction.finishes_at) + clockSkewMs : null
                }
                onPress={selectSlot}
              />
            );
          })}
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
    </ScrollView>
  );
}
