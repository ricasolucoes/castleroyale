import type { ReactNode } from 'react';
import { ActivityIndicator, View } from 'react-native';

import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';

export type SceneMessageProps = {
  /** `working` waits on something real; `problem` reports a state the player must act on. */
  tone: 'working' | 'problem';
  title: string;
  detail?: string;
  action?: ReactNode;
};

/**
 * What a scene shows instead of itself: loading, or a failure the player can
 * act on.
 *
 * Deliberately full-bleed and centred on the scene's own ground colour rather
 * than a bare spinner on a default background — a screen that drops to system
 * chrome mid-session reads as a broken app, not as a game fetching something.
 *
 * The spinner runs only while something genuinely is in flight; there is no
 * synthetic progress here, because none of these waits report progress.
 */
export function SceneMessage({ tone, title, detail, action }: SceneMessageProps) {
  const theme = useTheme();

  return (
    <View
      accessibilityRole="alert"
      accessibilityLabel={title}
      style={{
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        gap: theme.spacing.md,
        padding: theme.spacing.xl,
        backgroundColor: theme.color.bg.base,
      }}
    >
      {tone === 'working' && <ActivityIndicator color={theme.color.accent.bronze} />}
      <Text
        variant="heading"
        style={{ textAlign: 'center' }}
        color={tone === 'problem' ? theme.color.danger : theme.color.text.primary}
      >
        {title}
      </Text>
      {detail !== undefined && (
        <Text
          variant="caption"
          color={theme.color.text.secondary}
          style={{ textAlign: 'center' }}
        >
          {detail}
        </Text>
      )}
      {action}
    </View>
  );
}
