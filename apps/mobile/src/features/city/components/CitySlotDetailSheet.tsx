import { View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { router } from 'expo-router';
import type { CitySlot, Construction, ResourceBundle } from '@castleroyale/contracts';
import type { ResourceKey } from '@castleroyale/tooling/design-tokens';

import { ApiError } from '@/api/client';
import { useUpgradeBuilding } from '@/features/city/api/useUpgradeBuilding';
import { COST_SHORTFALL_ICON } from '@/shared/components/buildingIcons';
import { Badge } from '@/shared/components/Badge';
import { BottomSheet } from '@/shared/components/BottomSheet';
import { Button } from '@/shared/components/Button';
import { formatResourceAmount, formatResourceCost } from '@/shared/components/ResourceCounter';
import { RESOURCE_ICONS, RESOURCE_KEYS } from '@/shared/components/resourceIcons';
import { Text } from '@/shared/components/Text';
import { formatDuration, Timer } from '@/shared/components/Timer';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export type CitySlotDetailSheetProps = {
  slot: CitySlot | null;
  construction: Construction | null;
  serverTime: string;
  /** cityQuery.data.resources.current — the last server snapshot, never the ticked bar. */
  resources: ResourceBundle;
  activeConstructions: number;
  queueLimit: number;
  open: boolean;
  onClose: () => void;
  onConstructionFinish: () => void;
};

// Local on purpose: city.tsx's twin describes a failed *read*, this one a failed
// *mutation*. They will diverge (retry affordances differ) before they merge.
function upgradeErrorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.generic';
}

export function CitySlotDetailSheet({
  slot,
  construction,
  serverTime,
  resources,
  activeConstructions,
  queueLimit,
  open,
  onClose,
  onConstructionFinish,
}: CitySlotDetailSheetProps) {
  const theme = useTheme();
  const { t } = useTranslation();
  const upgrade = useUpgradeBuilding();
  const building = slot?.building ?? null;
  const showConstruction = building !== null && construction !== null;

  const isMax = building !== null && building.level >= building.max_level;
  const shortfalls = building
    ? RESOURCE_KEYS.filter(
        (resource) => (building.next_level_cost[resource] ?? 0) > (resources[resource] ?? 0),
      )
    : [];
  const affordable = shortfalls.length === 0;
  const queueFull =
    building !== null && !isMax && !showConstruction && activeConstructions >= queueLimit;

  const costText = building
    ? formatResourceCost(building.next_level_cost, (r) => t(`resources.${r}`))
    : '';
  const durationText = building ? formatDuration(building.build_time_seconds * 1000) : '';

  return (
    <BottomSheet
      accessibilityViewIsModal
      enablePanDownToClose
      index={open ? 0 : -1}
      onClose={onClose}
      snapPoints={[`${theme.spacing['3xl'] * 4}%`]}
    >
      <View style={{ gap: theme.spacing.md, padding: theme.spacing.lg }}>
        <Text accessibilityRole="header" variant="heading">
          {t('city.selected_slot')}
        </Text>
        {building === null ? (
          <View style={{ gap: theme.spacing.xs }}>
            <Text>{t('city.slot_empty')}</Text>
            <Text color={theme.color.text.secondary}>{t('city.slot_empty_hint')}</Text>
          </View>
        ) : (
          <View style={{ gap: theme.spacing.xs }}>
            <Text variant="label">{t(building.name_key)}</Text>
            <Text color={theme.color.text.secondary}>{t('building.level', { level: building.level })}</Text>
            <Badge variant="neutral" label={t('city.slot_category', { category: building.category })} />
            {showConstruction && construction && (
              <>
                <Text color={theme.color.text.secondary}>{t('city.construction_finish')}</Text>
                <Timer
                  targetTimestamp={
                    Date.parse(construction.finishes_at) + (Date.now() - Date.parse(serverTime))
                  }
                  onFinish={onConstructionFinish}
                />
              </>
            )}

            {isMax && <Badge variant="neutral" label={t('building.max_level')} />}

            {!isMax && !showConstruction && (
              <View
                accessible
                accessibilityLabel={`${t('building.cost_accessible', { cost: costText || t('building.no_cost') })} ${t('building.duration_accessible', { duration: durationText })}`}
                style={{ gap: theme.spacing.xs }}
              >
                {costText ? (
                  <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: theme.spacing.sm }}>
                    {RESOURCE_KEYS.filter((resource) => (building.next_level_cost[resource] ?? 0) > 0).map(
                      (resource: ResourceKey) => (
                        <View
                          key={resource}
                          style={{
                            flexDirection: 'row',
                            alignItems: 'center',
                            gap: theme.spacing.xs,
                          }}
                        >
                          <MaterialCommunityIcons
                            name={RESOURCE_ICONS[resource]}
                            size={12}
                            color={theme.resourceColors[resource]}
                          />
                          <Text variant="numeric" color={theme.resourceColors[resource]}>
                            {formatResourceAmount(building.next_level_cost[resource] ?? 0)}
                          </Text>
                          {shortfalls.includes(resource) && (
                            <MaterialCommunityIcons
                              name={COST_SHORTFALL_ICON}
                              size={12}
                              color={theme.color.text.secondary}
                            />
                          )}
                        </View>
                      ),
                    )}
                  </View>
                ) : (
                  <Text variant="caption">{t('building.no_cost')}</Text>
                )}
                <Text variant="numeric">{durationText}</Text>
              </View>
            )}

            {!isMax && !showConstruction && queueFull && (
              <Button variant="secondary" disabled title={t('building.queue_full')} />
            )}
            {!isMax && !showConstruction && !queueFull && !affordable && (
              <Button variant="secondary" disabled title={t('building.cannot_afford')} />
            )}
            {!isMax && !showConstruction && !queueFull && affordable && (
              <Button
                variant="primary"
                title={upgrade.isPending ? t('building.upgrading') : t('building.upgrade')}
                disabled={upgrade.isPending}
                accessibilityLabel={t('building.upgrade_accessible', { building: t(building.name_key) })}
                onPress={() => upgrade.mutate(building.code)}
              />
            )}

            {upgrade.error && (
              <Text color={theme.color.text.secondary}>{t(upgradeErrorKey(upgrade.error))}</Text>
            )}

            {building.code === 'academy' && (
              <Button
                variant="secondary"
                title={t('city.open_research')}
                onPress={() => router.push('/technology')}
              />
            )}
          </View>
        )}
      </View>
    </BottomSheet>
  );
}
