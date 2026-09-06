import { Pressable, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import type { CitySlot as CitySlotData } from '@castleroyale/contracts';

import { Badge } from '@/shared/components/Badge';
import { buildingIcon } from '@/shared/components/buildingIcons';
import { Text } from '@/shared/components/Text';
import { Timer } from '@/shared/components/Timer';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export type CitySlotTileProps = {
  slot: CitySlotData;
  size: number;
  isBuilding: boolean;
  /**
   * Skew-corrected `Construction.finishes_at`, in device epoch ms, when
   * `isBuilding` is true. `null` otherwise. Computed by the caller the same
   * way `CitySlotDetailSheet` corrects for device-clock skew — never the raw
   * server string, since the tile's `<Timer>` needs a device-clock deadline.
   */
  constructionFinishTimestamp: number | null;
  onPress: (slot: string) => void;
};

export function CitySlot({
  slot,
  size,
  isBuilding,
  constructionFinishTimestamp,
  onPress,
}: CitySlotTileProps) {
  const theme = useTheme();
  const { t } = useTranslation();
  const building = slot.building;

  const accessibilityLabel =
    building === null
      ? t('city.slot_accessible_empty')
      : t('city.slot_accessible_occupied', { building: t(building.name_key), level: building.level });

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      hitSlop={Math.max(0, (theme.minTouchTarget - size) / 2)}
      onPress={() => onPress(slot.slot)}
      style={{
        width: size,
        height: size,
        backgroundColor: building === null ? theme.color.bg.sunken : theme.color.surface.raised,
        borderWidth: 1,
        borderStyle: building === null ? 'dashed' : 'solid',
        borderColor: building === null ? theme.color.border.subtle : theme.color.border.strong,
        borderRadius: theme.radius.md,
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      {building === null ? (
        <MaterialCommunityIcons
          name="plus-circle-outline"
          size={theme.spacing.xl}
          color={theme.color.accent.gold}
        />
      ) : (
        <View
          style={{
            gap: theme.spacing.xs,
            alignItems: 'center',
            justifyContent: 'center',
            padding: theme.spacing.xs,
          }}
        >
          <MaterialCommunityIcons
            name={buildingIcon(building.code)}
            size={theme.spacing.xl}
            color={theme.color.text.secondary}
          />
          <Text variant="label" numberOfLines={1}>
            {t(building.name_key)}
          </Text>
          <Badge variant="neutral" label={String(building.level)} />
          {isBuilding && constructionFinishTimestamp !== null && (
            <Timer targetTimestamp={constructionFinishTimestamp} />
          )}
        </View>
      )}
    </Pressable>
  );
}
