import React from 'react';
import { type ViewStyle } from 'react-native';
import { useTheme } from '@/theme';
import { Box, type BoxProps } from './Box';

export interface CardProps extends BoxProps {
  style?: ViewStyle;
}

export function Card({ style, children, ...rest }: CardProps) {
  const theme = useTheme();
  return (
    <Box
      p="md"
      borderRadius="md"
      backgroundColor={theme.color.surface.raised}
      style={[
        {
          borderWidth: 1,
          borderColor: theme.color.border.subtle,
          // Subtle elevation shadow
          shadowColor: '#000',
          shadowOffset: { width: 0, height: 1 },
          shadowOpacity: 0.1,
          shadowRadius: 2,
          elevation: theme.elevation.raised,
        },
        style,
      ]}
      {...rest}
    >
      {children}
    </Box>
  );
}
