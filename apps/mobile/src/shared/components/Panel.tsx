import React from 'react';
import { type ViewStyle } from 'react-native';
import { useTheme } from '@/theme';
import { Box, type BoxProps } from './Box';

export interface PanelProps extends BoxProps {
  style?: ViewStyle;
}

export function Panel({ style, children, ...rest }: PanelProps) {
  const theme = useTheme();
  return (
    <Box
      p="md"
      backgroundColor={theme.color.bg.sunken}
      borderRadius="md"
      style={[
        {
          borderWidth: 1,
          borderColor: theme.color.border.strong,
        },
        style,
      ]}
      {...rest}
    >
      {children}
    </Box>
  );
}
