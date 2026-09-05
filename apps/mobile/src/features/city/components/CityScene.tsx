import { useState } from 'react';
import { RefreshControl, ScrollView, View } from 'react-native';
import type { CityData } from '@castleroyale/contracts';

import { computeSlotLayout } from '@/features/city/rendering/grid';
import { useCitySelectionStore } from '@/features/city/state/citySelectionStore';
import { CitySlot } from './CitySlot';
import { CitySlotDetailSheet } from './CitySlotDetailSheet';
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
  const constructionFinishTimestamp = city.construction
    ? Date.parse(city.construction.finishes_at) + (Date.now() - Date.parse(city.server_time))
    : null;

  const selected = city.slots.find((s) => s.slot === selectedSlot) ?? null;

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
            const isBuilding =
              slot.building !== null && city.construction?.building_code === slot.building.code;

            return (
              <CitySlot
                key={slot.slot}
                slot={slot}
                size={layout.tileSize}
                isBuilding={isBuilding}
                constructionFinishTimestamp={isBuilding ? constructionFinishTimestamp : null}
                onPress={selectSlot}
              />
            );
          })}
      </View>

      <CitySlotDetailSheet
        slot={selected}
        construction={city.construction}
        serverTime={city.server_time}
        open={selectedSlot !== null}
        onClose={clearSelection}
        onConstructionFinish={onRefresh}
      />
    </ScrollView>
  );
}
