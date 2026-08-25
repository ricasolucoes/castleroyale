import React from 'react';
import { type ViewStyle } from 'react-native';
import { useTheme } from '@/theme';
import { Box } from './Box';
import { Text } from './Text';

export interface BadgeProps {
  label: string;
  variant?: 'neutral' | 'success' | 'warning' | 'danger';
  style?: ViewStyle;
}

export function Badge({ label, variant = 'neutral', style }: BadgeProps) {
  const theme = useTheme();

  const getBackgroundColor = () => {
    switch (variant) {
      case 'success':
        return theme.color.success;
      case 'warning':
        return theme.color.warning;
      case 'danger':
        return theme.color.danger;
      case 'neutral':
      default:
        return theme.color.surface.overlay;
    }
  };

  return (
    <Box
      px="sm"
      py="xs"
      borderRadius="sm"
      style={[
        {
          backgroundColor: getBackgroundColor(),
          alignSelf: 'flex-start',
        },
        style,
      ]}
    >
      <Text variant="caption" color={theme.color.text.inverse}>
        {label}
      </Text>
    </Box>
  );
}
