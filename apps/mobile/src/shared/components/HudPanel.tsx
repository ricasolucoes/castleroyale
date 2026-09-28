import type { ReactNode } from 'react';
import { View, type ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useTheme } from '@/theme';

export type HudCorner = 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';

export type HudPanelProps = {
  corner: HudCorner;
  children: ReactNode;
  style?: ViewStyle;
};

/**
 * A readout floating over a live scene.
 *
 * Two rules make this a HUD rather than a card:
 *
 * 1. It is transparent to touch. `pointerEvents="none"` means a thumb dragged
 *    across the panel pans the map underneath it — the panel reports state, it
 *    never intercepts navigation. Anything interactive belongs in its own
 *    control, not in here.
 * 2. It sits in a screen corner and never in the middle, so the centre of the
 *    screen stays free for the world.
 *
 * The horizontal safe-area inset is honoured because a rounded display clips
 * corners exactly where this panel lives.
 */
export function HudPanel({ corner, children, style }: HudPanelProps) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const [vertical, horizontal] = corner.split('-') as ['top' | 'bottom', 'left' | 'right'];

  return (
    <View
      pointerEvents="none"
      style={[
        {
          position: 'absolute',
          maxWidth: '58%',
          gap: theme.spacing.xs / 2,
          paddingVertical: theme.spacing.sm,
          paddingHorizontal: theme.spacing.md,
          borderRadius: theme.radius.md,
          borderWidth: 1,
          borderColor: theme.color.hud.edge,
          backgroundColor: theme.color.hud.panel,
          // The top inset is already owned by the resource bar mounted above
          // the tab navigator, so only the sides and the bottom are added
          // here — adding it again would count the notch twice.
          [vertical]: theme.spacing.lg,
          [horizontal]:
            theme.spacing.lg + (horizontal === 'left' ? insets.left : insets.right),
          ...(vertical === 'bottom' ? { marginBottom: insets.bottom } : null),
        } as ViewStyle,
        style,
      ]}
    >
      {children}
    </View>
  );
}
