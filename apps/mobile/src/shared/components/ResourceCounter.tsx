import React from 'react';
import { View, type ViewProps } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Text } from './Text';
import { STORAGE_FULL_ICON } from './resourceIcons';
import { useTheme } from '@/theme';
import type { ResourceKey } from '@castleroyale/tooling/design-tokens';

export interface ResourceCounterProps extends ViewProps {
  resource: ResourceKey;
  amount: number;
  icon?: React.ReactNode;
  /** When present, renders the 4pt capacity meter below the numeral row. */
  capacity?: number;
  /** When true, appends the storage-full glyph and the MAX caption. */
  isFull?: boolean;
  /**
   * The localised "storage full" caption (e.g. "MAX"). Passed down rather than
   * looked up here so this component keeps no i18n dependency of its own.
   */
  fullLabel?: string;
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

export function ResourceCounter({
  resource,
  amount,
  icon,
  capacity,
  isFull,
  fullLabel,
  style,
  ...rest
}: ResourceCounterProps) {
  const theme = useTheme();
  const hasMeter = typeof capacity === 'number';
  const percent = hasMeter && capacity > 0 ? Math.min(100, Math.max(0, (amount / capacity) * 100)) : 0;

  return (
    <View style={[{ gap: theme.spacing.xs }, style]} {...rest}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          backgroundColor: theme.color.surface.raised,
          paddingHorizontal: theme.spacing.sm,
          paddingVertical: theme.spacing.xs,
          borderRadius: theme.radius.sm,
          borderWidth: 1,
          borderColor: theme.color.border.subtle,
        }}
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
        {isFull && (
          <View style={{ marginLeft: theme.spacing.xs }}>
            <MaterialCommunityIcons
              name={STORAGE_FULL_ICON}
              size={12}
              color={theme.color.text.secondary}
            />
          </View>
        )}
      </View>
      {hasMeter && (
        <View
          style={{
            height: theme.spacing.xs,
            width: '100%',
            backgroundColor: theme.color.border.subtle,
            borderRadius: theme.radius.full,
            overflow: 'hidden',
          }}
        >
          <View
            style={{
              height: '100%',
              width: `${percent}%`,
              backgroundColor: theme.resourceColors[resource],
            }}
          />
        </View>
      )}
      {isFull && fullLabel && (
        <Text variant="caption" color={theme.color.text.secondary}>
          {fullLabel}
        </Text>
      )}
    </View>
  );
}
