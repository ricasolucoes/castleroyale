import React from 'react';
import { Pressable, type PressableProps, type ViewStyle, StyleSheet } from 'react-native';
import { useTheme } from '@/theme';
import { Box } from './Box';
import { Text } from './Text';

export type ButtonVariant = 'primary' | 'secondary' | 'danger';

export interface ButtonProps extends Omit<PressableProps, 'style'> {
  title: string;
  variant?: ButtonVariant;
  style?: ViewStyle;
}

export function Button({ title, variant = 'primary', style, ...rest }: ButtonProps) {
  const theme = useTheme();

  const getBackgroundColor = (pressed: boolean) => {
    switch (variant) {
      case 'primary':
        return theme.color.accent.bronze;
      case 'secondary':
        return theme.color.surface.raised;
      case 'danger':
        return theme.color.danger;
      default:
        // Fallback mentioned in plan "Use colors from theme.color.bg" just in case:
        return theme.color.bg.base;
    }
  };

  const getTextColor = () => {
    switch (variant) {
      case 'primary':
      case 'danger':
        return theme.color.text.inverse;
      case 'secondary':
        return theme.color.text.primary;
      default:
        return theme.color.text.primary;
    }
  };

  return (
    <Pressable style={style} {...rest}>
      {({ pressed }) => (
        <Box
          px="lg"
          py="md"
          borderRadius="md"
          style={[
            {
              minHeight: theme.minTouchTarget,
              alignItems: 'center',
              justifyContent: 'center',
              backgroundColor: getBackgroundColor(pressed),
              opacity: pressed ? 0.8 : 1,
            },
            variant === 'secondary' && {
              borderWidth: 1,
              borderColor: theme.color.border.subtle,
            },
          ]}
        >
          <Text variant="label" color={getTextColor()}>
            {title}
          </Text>
        </Box>
      )}
    </Pressable>
  );
}
