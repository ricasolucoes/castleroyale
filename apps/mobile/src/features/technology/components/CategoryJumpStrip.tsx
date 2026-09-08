import { Pressable, ScrollView } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';

import { Text } from '@/shared/components/Text';
import { technologyIcon } from '@/shared/components/technologyIcons';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

/**
 * The fixed order docs/game-design/technology.md declares — a stable order the
 * player learns once, not a dynamic sort. All 8 chips render regardless of how
 * many technologies a category currently holds; nothing is hidden or filtered
 * by this strip, only navigated to.
 */
export const CATEGORY_ORDER = [
  'economy',
  'military',
  'defense',
  'logistics',
  'construction',
  'exploration',
  'alliance',
  'siege',
] as const;

export type CategoryJumpStripProps = {
  onSelect: (category: string) => void;
};

/**
 * Sticky navigation to a section's measured offset — convenience only, nothing
 * is hidden. Each chip clears 44x44pt via an explicit `minHeight` plus
 * `hitSlop`, because `__tests__/touch-targets.test.tsx` exercises `Button` and
 * `BottomSheet` directly and will not catch an undersized bespoke `Pressable`
 * built for this screen (10-UI-SPEC.md's Layout Strategy §6).
 */
export function CategoryJumpStrip({ onSelect }: CategoryJumpStripProps) {
  const theme = useTheme();
  const { t } = useTranslation();

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={{
        flexDirection: 'row',
        gap: theme.spacing.sm,
        paddingHorizontal: theme.spacing.lg,
        paddingVertical: theme.spacing.sm,
      }}
    >
      {CATEGORY_ORDER.map((category) => (
        <Pressable
          key={category}
          accessibilityRole="button"
          hitSlop={theme.spacing.sm}
          onPress={() => onSelect(category)}
          style={{
            minHeight: theme.minTouchTarget,
            flexDirection: 'row',
            alignItems: 'center',
            gap: theme.spacing.xs,
            paddingHorizontal: theme.spacing.sm,
            borderRadius: theme.radius.md,
            borderWidth: 1,
            borderColor: theme.color.border.subtle,
            backgroundColor: theme.color.surface.raised,
          }}
        >
          <MaterialCommunityIcons
            name={technologyIcon(category)}
            size={16}
            color={theme.color.text.secondary}
          />
          <Text variant="label">{t(`technology.category_${category}`)}</Text>
        </Pressable>
      ))}
    </ScrollView>
  );
}
