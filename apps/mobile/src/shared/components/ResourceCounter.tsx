import React from 'react';
import { View, type ViewProps } from 'react-native';
import { Text } from './Text';
import { useTheme } from '@/theme';
import type { ResourceKey } from '@dominion/tooling/design-tokens';

export interface ResourceCounterProps extends ViewProps {
  resource: ResourceKey;
  amount: number;
  icon?: React.ReactNode;
}

export function formatResourceAmount(amount: number): string {
  if (amount >= 1000000) {
    return (amount / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
  }
  if (amount >= 1000) {
    return (amount / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
  }
  return Math.floor(amount).toString();
}

export function ResourceCounter({ resource, amount, icon, style, ...rest }: ResourceCounterProps) {
  const theme = useTheme();

  return (
    <View
      style={[
        {
          flexDirection: 'row',
          alignItems: 'center',
          backgroundColor: theme.color.surface.raised,
          paddingHorizontal: theme.spacing.sm,
          paddingVertical: theme.spacing.xs,
          borderRadius: theme.radius.sm,
          borderWidth: 1,
          borderColor: theme.color.border.subtle,
        },
        style,
      ]}
      {...rest}
    >
      {icon && (
        <View style={{ marginRight: theme.spacing.xs }}>
          {icon}
        </View>
      )}
      <Text
        variant="numeric"
        color={theme.resourceColors[resource]}
      >
        {formatResourceAmount(amount)}
      </Text>
    </View>
  );
}
