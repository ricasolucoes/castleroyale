import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import type { ResourceBundle } from '@castleroyale/contracts';

import { useCityQuery } from '@/features/city/api/useCityQuery';
import {
  interpolateResources,
  type ResourceSnapshot,
} from '@/features/economy/interpolation/interpolateResources';
import { RESOURCE_ICONS, RESOURCE_KEYS } from '@/shared/components/resourceIcons';
import { ResourceCounter } from '@/shared/components/ResourceCounter';
import { Skeleton } from '@/shared/components/Skeleton';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

/**
 * The persistent HUD strip mounted above the tab navigator
 * (see `app/(tabs)/_layout.tsx`). Player-level chrome, not a city-tab-only
 * concern — see 08-UI-SPEC.md § Placement.
 *
 * Owns the top safe-area inset: `CityScene` used to add `insets.top` itself,
 * but this bar now sits above every tab, so it is the one place the notch is
 * accounted for.
 */
export function ResourceBar() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();
  const cityQuery = useCityQuery();
  const [displayed, setDisplayed] = useState<ResourceBundle | null>(null);

  useEffect(() => {
    const data = cityQuery.data;
    if (!data) return;

    const snapshot: ResourceSnapshot = {
      current: data.resources.current,
      capacity: data.resources.capacity,
      rate: data.resources.rate,
      capturedAt: cityQuery.dataUpdatedAt,
    };

    // Rule 1 (08-UI-SPEC.md): every successful read resets the baseline
    // instantly, with no easing toward the corrected value.
    setDisplayed(interpolateResources(snapshot, Date.now()));

    // Rule 4: a failed background poll freezes the last computed values —
    // never extrapolate across a connectivity gap the server has not
    // confirmed it was accruing through.
    if (cityQuery.isError) return;

    const interval = setInterval(() => {
      setDisplayed(interpolateResources(snapshot, Date.now()));
    }, 1000);

    return () => clearInterval(interval);
  }, [cityQuery.data, cityQuery.dataUpdatedAt, cityQuery.isError]);

  const containerStyle = {
    flexDirection: 'row' as const,
    justifyContent: 'space-between' as const,
    paddingTop: insets.top + theme.spacing.xs,
    paddingBottom: theme.spacing.xs,
    paddingHorizontal: theme.spacing.sm,
    gap: theme.spacing.xs,
    backgroundColor: theme.color.surface.raised,
    borderBottomWidth: 1,
    borderBottomColor: theme.color.border.strong,
  };

  // First load: no cached data yet, a fetch is in flight.
  if (cityQuery.isPending) {
    return (
      <View accessibilityLabel={t('resources.bar_accessibility')} style={containerStyle}>
        {RESOURCE_KEYS.map((resource) => (
          <Skeleton key={resource} height={20} borderRadius={theme.radius.sm} style={{ flex: 1 }} />
        ))}
      </View>
    );
  }

  // The query has never succeeded (first load failed) — the underlying
  // screen already owns full-screen error + retry messaging, so the bar has
  // no redundant retry affordance of its own to offer.
  if (!cityQuery.data || !displayed) return null;

  const { capacity } = cityQuery.data.resources;

  return (
    <View
      accessibilityLabel={t('resources.bar_accessibility')}
      style={[containerStyle, { opacity: cityQuery.isError ? 0.6 : 1 }]}
    >
      {RESOURCE_KEYS.map((resource) => {
        const amount = displayed[resource] ?? 0;
        const resourceCapacity = capacity[resource] ?? 0;
        const isFull = resourceCapacity > 0 && amount >= resourceCapacity;
        const resourceName = t(`resources.${resource}`);

        return (
          <ResourceCounter
            key={resource}
            resource={resource}
            amount={amount}
            capacity={resourceCapacity}
            isFull={isFull}
            fullLabel={t('resources.storage_full_short')}
            icon={
              <MaterialCommunityIcons
                name={RESOURCE_ICONS[resource]}
                size={16}
                color={theme.resourceColors[resource]}
              />
            }
            style={{ flex: 1 }}
            accessible
            accessibilityLabel={
              isFull
                ? t('resources.accessible_full', { resource: resourceName, capacity: resourceCapacity })
                : t('resources.accessible_reading', {
                    resource: resourceName,
                    amount,
                    capacity: resourceCapacity,
                  })
            }
          />
        );
      })}
    </View>
  );
}
