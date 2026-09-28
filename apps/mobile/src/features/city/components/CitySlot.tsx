import { Image, Pressable, View } from 'react-native';
import type { CitySlot as CitySlotData } from '@castleroyale/contracts';

import { getBuildingAsset } from '@/features/city/buildingAssets';
import { selectionFeedback } from '@/shared/feedback/haptics';
import { Badge } from '@/shared/components/Badge';
import { Text } from '@/shared/components/Text';
import { Timer } from '@/shared/components/Timer';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

/**
 * Below this the plot is a token, not a picture: a name caption at 12pt inside
 * a tile this small collides with the level badge and reads as a form field.
 * The name is still on the accessibility label at every size.
 */
const CAPTION_MIN_TILE = 76;

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
  isSelected?: boolean;
  onPress: (slot: string) => void;
};

export function CitySlot({
  slot,
  size,
  isBuilding,
  constructionFinishTimestamp,
  isSelected = false,
  onPress,
}: CitySlotTileProps) {
  const theme = useTheme();
  const { t } = useTranslation();
  const building = slot.building;

  const accessibilityLabel =
    building === null
      ? t('city.slot_accessible_empty')
      : t('city.slot_accessible_occupied', { building: t(building.name_key), level: building.level });

  // The generated artwork for this building at this level. Buildings without a
  // rendered level fall back to the category glyph rather than to nothing —
  // the catalogue grows faster than the art does.
  const artwork = building === null ? null : getBuildingAsset(building.code, building.level);
  const showsCaption = size >= CAPTION_MIN_TILE;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      accessibilityState={{ selected: isSelected }}
      hitSlop={Math.max(0, (theme.minTouchTarget - size) / 2)}
      onPress={() => {
        selectionFeedback();
        onPress(slot.slot);
      }}
      style={{
        width: size,
        height: size,
        alignItems: 'center',
        justifyContent: 'center',
        padding: theme.spacing.xs / 2,
      }}
    >
      {({ pressed }) => (
        <View
          style={{
            flex: 1,
            alignSelf: 'stretch',
            alignItems: 'center',
            justifyContent: 'center',
            borderRadius: theme.radius.md,
            overflow: 'hidden',
            // An empty plot is surveyed ground: a dashed outline over the
            // terrain. An occupied one is a plinth the building stands on.
            // Neither is an opaque card any more — the generated ground the
            // scene draws underneath used to be completely hidden by these.
            borderWidth: building === null || isSelected ? 1 : 0,
            borderStyle: building === null ? 'dashed' : 'solid',
            borderColor: isSelected ? theme.color.accent.gold : theme.color.border.subtle,
            backgroundColor: building === null ? 'transparent' : theme.color.bg.sunken,
            opacity: pressed ? 0.75 : 1,
          }}
        >
          {building === null ? (
            <Image
              // eslint-disable-next-line @typescript-eslint/no-require-imports
              source={require('../../../../assets/city/slot_empty_icon.png')}
              style={{
                width: theme.spacing.xl,
                height: theme.spacing.xl,
                tintColor: theme.color.accent.gold,
                opacity: 0.7,
              }}
              resizeMode="contain"
            />
          ) : (
            <>
              {artwork === null ? (
                <Image
                  source={
                    building.category === 'core'
                      // eslint-disable-next-line @typescript-eslint/no-require-imports
                      ? require('../../../../assets/city/slot_category_core.png')
                      // eslint-disable-next-line @typescript-eslint/no-require-imports
                      : require('../../../../assets/city/slot_category_economy.png')
                  }
                  style={{
                    width: theme.spacing.xl,
                    height: theme.spacing.xl,
                    tintColor: theme.color.text.secondary,
                  }}
                  resizeMode="contain"
                />
              ) : (
                <Image
                  accessibilityLabel={t('city.building_image_accessibility', {
                    building: t(building.name_key),
                  })}
                  source={artwork}
                  style={{ width: '100%', height: '100%' }}
                  resizeMode="cover"
                />
              )}

              {/* Level, always. Bottom-left so it never lands on the timer. */}
              <View
                style={{
                  position: 'absolute',
                  left: theme.spacing.xs / 2,
                  bottom: theme.spacing.xs / 2,
                }}
              >
                <Badge variant="neutral" label={String(building.level)} />
              </View>

              {isBuilding && constructionFinishTimestamp !== null && (
                <View
                  style={{
                    position: 'absolute',
                    top: theme.spacing.xs / 2,
                    right: theme.spacing.xs / 2,
                    paddingHorizontal: theme.spacing.xs,
                    borderRadius: theme.radius.sm,
                    backgroundColor: theme.color.hud.panel,
                  }}
                >
                  <Timer targetTimestamp={constructionFinishTimestamp} />
                </View>
              )}

              {showsCaption && (
                <View
                  style={{
                    position: 'absolute',
                    left: 0,
                    right: 0,
                    bottom: 0,
                    paddingVertical: theme.spacing.xs / 2,
                    paddingLeft: theme.spacing.xl,
                    paddingRight: theme.spacing.xs,
                    backgroundColor: theme.color.hud.panel,
                  }}
                >
                  <Text variant="caption" numberOfLines={1}>
                    {t(building.name_key)}
                  </Text>
                </View>
              )}
            </>
          )}
        </View>
      )}
    </Pressable>
  );
}
