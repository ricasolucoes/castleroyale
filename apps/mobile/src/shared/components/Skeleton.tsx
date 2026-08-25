import React, { useEffect, useRef } from 'react';
import { Animated, type ViewStyle } from 'react-native';
import { useTheme } from '@/theme';

export interface SkeletonProps {
  width?: number | string;
  height?: number | string;
  borderRadius?: number;
  style?: ViewStyle;
}

export function Skeleton({ width = '100%', height = 20, borderRadius = 4, style }: SkeletonProps) {
  const theme = useTheme();
  const opacity = useRef(new Animated.Value(0.3)).current;

  useEffect(() => {
    // Only animate if the theme's motion allows it (assuming reduce-motion check or similar,
    // though tokens use 0 for instant, Animated loop ignores tokens without custom logic.
    // For a simple implementation, we'll loop it unless motion is instant).
    if (theme.motion.base > 0) {
      Animated.loop(
        Animated.sequence([
          Animated.timing(opacity, {
            toValue: 0.7,
            duration: 800,
            useNativeDriver: true,
          }),
          Animated.timing(opacity, {
            toValue: 0.3,
            duration: 800,
            useNativeDriver: true,
          }),
        ])
      ).start();
    }
  }, [opacity, theme.motion.base]);

  return (
    <Animated.View
      style={[
        {
          width,
          height,
          borderRadius,
          backgroundColor: theme.color.border.subtle,
          opacity,
        },
        style,
      ]}
    />
  );
}
