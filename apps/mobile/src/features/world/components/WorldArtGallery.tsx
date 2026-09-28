import { Image, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

const WORLD_ART = [
  {
    labelKey: 'world.art_world_overview',
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    source: require('../../../../assets/map/world-overview.png'),
  },
  {
    labelKey: 'world.art_region_detail',
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    source: require('../../../../assets/map/region-detail.png'),
  },
  {
    labelKey: 'world.art_marker_atlas',
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    source: require('../../../../assets/map/marker-atlas.png'),
  },
  {
    labelKey: 'world.art_terrain_atlas',
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    source: require('../../../../assets/map/terrain-atlas.png'),
  },
] as const;

export type WorldArtGalleryProps = {
  open?: boolean;
  onClose?: () => void;
};

export function WorldArtGallery({ open = true, onClose }: WorldArtGalleryProps) {
  const theme = useTheme();
  const { t } = useTranslation();

  if (!open) {
    return null;
  }

  const styles = StyleSheet.create({
    backdrop: {
      ...StyleSheet.absoluteFill,
      backgroundColor: 'rgba(5, 7, 10, 0.82)',
      zIndex: 50,
      justifyContent: 'center',
      alignItems: 'center',
      padding: theme.spacing.lg,
    },
    modal: {
      width: '100%',
      maxWidth: 480,
      maxHeight: '85%',
      backgroundColor: theme.color.hud.panel,
      borderRadius: theme.radius.lg,
      borderWidth: 1,
      borderColor: theme.color.hud.edge,
      padding: theme.spacing.lg,
      gap: theme.spacing.md,
    },
    header: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'flex-start',
      gap: theme.spacing.md,
    },
    titleGroup: {
      flex: 1,
      gap: theme.spacing.xs,
    },
    closeButton: {
      minWidth: theme.minTouchTarget,
      minHeight: theme.minTouchTarget,
      alignItems: 'center',
      justifyContent: 'center',
      borderRadius: theme.radius.full,
      backgroundColor: theme.color.bg.sunken,
      borderWidth: 1,
      borderColor: theme.color.border.subtle,
      paddingHorizontal: theme.spacing.sm,
    },
    scrollContent: {
      gap: theme.spacing.md,
      paddingBottom: theme.spacing.sm,
    },
    grid: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: theme.spacing.md,
    },
    item: {
      width: '47%',
      flexGrow: 1,
      gap: theme.spacing.xs,
      backgroundColor: theme.color.bg.sunken,
      padding: theme.spacing.sm,
      borderRadius: theme.radius.md,
      borderWidth: 1,
      borderColor: theme.color.border.subtle,
    },
    image: {
      width: '100%',
      aspectRatio: 1.35,
      borderRadius: theme.radius.sm,
      backgroundColor: theme.color.bg.base,
    },
  });

  return (
    <View style={styles.backdrop}>
      <View style={styles.modal}>
        <View style={styles.header}>
          <View style={styles.titleGroup}>
            <Text variant="heading" color={theme.color.accent.gold}>
              {t('world.art_title')}
            </Text>
            <Text variant="caption" color={theme.color.text.secondary}>
              {t('world.art_copy')}
            </Text>
          </View>
          {onClose && (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={t('common.close')}
              onPress={onClose}
              style={({ pressed }) => [styles.closeButton, { opacity: pressed ? 0.7 : 1 }]}
            >
              <Text variant="label" color={theme.color.text.secondary}>
                ✕
              </Text>
            </Pressable>
          )}
        </View>

        <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
          <View style={styles.grid}>
            {WORLD_ART.map((asset) => (
              <View key={asset.labelKey} style={styles.item}>
                <Image
                  accessibilityLabel={t(asset.labelKey)}
                  source={asset.source}
                  resizeMode="cover"
                  style={styles.image}
                />
                <Text variant="caption" numberOfLines={2}>
                  {t(asset.labelKey)}
                </Text>
              </View>
            ))}
          </View>
        </ScrollView>
      </View>
    </View>
  );
}
