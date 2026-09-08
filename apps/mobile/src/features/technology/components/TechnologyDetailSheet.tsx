import { View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import type { ActiveResearch, ResourceBundle, Technology, TechnologyEffect } from '@castleroyale/contracts';
import type { ResourceKey } from '@castleroyale/tooling/design-tokens';

import { ApiError } from '@/api/client';
import { useResearchTechnology } from '@/features/technology/api/useResearchTechnology';
import { COST_SHORTFALL_ICON } from '@/shared/components/buildingIcons';
import { Badge } from '@/shared/components/Badge';
import { BottomSheet } from '@/shared/components/BottomSheet';
import { Button } from '@/shared/components/Button';
import { formatResourceAmount, formatResourceCost } from '@/shared/components/ResourceCounter';
import { RESOURCE_ICONS, RESOURCE_KEYS } from '@/shared/components/resourceIcons';
import { Text } from '@/shared/components/Text';
import { formatDuration, Timer } from '@/shared/components/Timer';
import { useTheme } from '@/theme';
import { useTranslation, type TranslateFn } from '@/i18n/useTranslation';

export type TechnologyDetailSheetProps = {
  technology: Technology | null;
  /** Resolves any code (this technology's own prerequisites) to its full record. */
  technologyByCode: Map<string, Technology>;
  activeResearch: ActiveResearch | null;
  serverTime: string;
  /** `city.resources.current` — the last server snapshot, never the ticked bar. */
  resources: ResourceBundle;
  open: boolean;
  onClose: () => void;
};

// Local on purpose, mirroring CitySlotDetailSheet's own `upgradeErrorKey`: a
// failed mutation, not a failed read, so the two will diverge before they merge.
function researchErrorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.generic';
}

/**
 * The effect row's sign convention (Flagged Assumption 1, resolved by 10-01):
 * a `multiply` effect's `value` is full permille, where 1000 is the identity
 * (1100 = +10%, 900 = -10%) — never the raw permille number. An `add` effect
 * renders its value directly with a sign.
 */
function formatEffect(effect: TechnologyEffect, t: TranslateFn): string {
  const key = `technology.effect_target.${effect.target.replace(/\./g, '_')}`;
  const label = t(key);

  if (effect.operation === 'multiply') {
    const percent = Math.round((effect.value - 1000) / 10);
    return `${percent >= 0 ? '+' : ''}${percent}% ${label}`;
  }

  return `${effect.value >= 0 ? '+' : ''}${effect.value} ${label}`;
}

/**
 * Directly extends the `BottomSheet` pattern `CitySlotDetailSheet` already
 * established — same component, unmodified, new content. Renders exactly one
 * of six CTA states, in 10-UI-SPEC.md §D's precedence order.
 */
export function TechnologyDetailSheet({
  technology,
  technologyByCode,
  activeResearch,
  serverTime,
  resources,
  open,
  onClose,
}: TechnologyDetailSheetProps) {
  const theme = useTheme();
  const { t } = useTranslation();
  const research = useResearchTechnology();

  const isMax = technology !== null && technology.level >= technology.max_level;
  const isThisTechActive = activeResearch?.technology_code === technology?.code;
  const isLocked =
    technology !== null &&
    technology.prerequisites.some(
      (prerequisite) => (technologyByCode.get(prerequisite.code)?.level ?? 0) < prerequisite.level,
    );
  const busyElsewhere = activeResearch !== null && !isThisTechActive;
  const nextLevel = technology?.next_level ?? null;
  const shortfalls = nextLevel
    ? RESOURCE_KEYS.filter((resource) => (nextLevel.cost[resource] ?? 0) > (resources[resource] ?? 0))
    : [];
  const affordable = shortfalls.length === 0;

  const finishTimestamp =
    isThisTechActive && activeResearch
      ? Date.parse(activeResearch.finishes_at) + (Date.now() - Date.parse(serverTime))
      : null;

  const costText = nextLevel ? formatResourceCost(nextLevel.cost, (r) => t(`resources.${r}`)) : '';
  const durationText = nextLevel ? formatDuration(nextLevel.research_time_seconds * 1000) : '';

  return (
    <BottomSheet
      accessibilityViewIsModal
      enablePanDownToClose
      index={open ? 0 : -1}
      onClose={onClose}
      snapPoints={[`${theme.spacing['3xl'] * 4}%`]}
    >
      <View style={{ gap: theme.spacing.md, padding: theme.spacing.lg }}>
        {technology === null ? (
          <Text accessibilityRole="header" variant="heading">
            {t('technology.tree_title')}
          </Text>
        ) : (
          <View style={{ gap: theme.spacing.xs }}>
            <Text accessibilityRole="header" variant="heading">
              {t(technology.name_key)}
            </Text>
            <Badge variant="neutral" label={`${technology.level}/${technology.max_level}`} />

            {!isMax && !isThisTechActive && technology.prerequisites.length > 0 && (
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: theme.spacing.sm }}>
                {technology.prerequisites.map((prerequisite) => {
                  const prerequisiteTech = technologyByCode.get(prerequisite.code);
                  const satisfied = (prerequisiteTech?.level ?? 0) >= prerequisite.level;
                  const name = prerequisiteTech ? t(prerequisiteTech.name_key) : prerequisite.code;
                  const categoryLabel = prerequisiteTech
                    ? t(`technology.category_${prerequisiteTech.category}`)
                    : '';

                  return (
                    <View
                      key={prerequisite.code}
                      style={{ flexDirection: 'row', alignItems: 'center', gap: theme.spacing.xs }}
                    >
                      {satisfied && (
                        <MaterialCommunityIcons
                          name="check-circle-outline"
                          size={12}
                          color={theme.color.text.secondary}
                        />
                      )}
                      <Text variant="caption" color={theme.color.text.secondary}>
                        {`${name} · ${categoryLabel}`}
                      </Text>
                    </View>
                  );
                })}
              </View>
            )}

            {isMax && <Badge variant="neutral" label={t('technology.max_level')} />}

            {isThisTechActive && finishTimestamp !== null && <Timer targetTimestamp={finishTimestamp} />}

            {!isMax && !isThisTechActive && nextLevel && (
              <View
                accessible
                accessibilityLabel={`${t('technology.cost_accessible', { cost: costText || t('technology.no_cost') })} ${t('technology.duration_accessible', { duration: durationText })}`}
                style={{ gap: theme.spacing.xs }}
              >
                {costText ? (
                  <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: theme.spacing.sm }}>
                    {RESOURCE_KEYS.filter((resource) => (nextLevel.cost[resource] ?? 0) > 0).map(
                      (resource: ResourceKey) => (
                        <View
                          key={resource}
                          style={{ flexDirection: 'row', alignItems: 'center', gap: theme.spacing.xs }}
                        >
                          <MaterialCommunityIcons
                            name={RESOURCE_ICONS[resource]}
                            size={12}
                            color={theme.resourceColors[resource]}
                          />
                          <Text variant="numeric" color={theme.resourceColors[resource]}>
                            {formatResourceAmount(nextLevel.cost[resource] ?? 0)}
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
                  <Text variant="caption">{t('technology.no_cost')}</Text>
                )}
                <Text variant="numeric">{durationText}</Text>
                {nextLevel.effects.map((effect) => (
                  <Text key={effect.target} variant="caption" color={theme.color.text.secondary}>
                    {formatEffect(effect, t)}
                  </Text>
                ))}
              </View>
            )}

            {!isMax && !isThisTechActive && isLocked && (
              <Button variant="secondary" disabled title={t('technology.locked')} />
            )}
            {!isMax && !isThisTechActive && !isLocked && busyElsewhere && (
              <Button variant="secondary" disabled title={t('technology.busy')} />
            )}
            {!isMax && !isThisTechActive && !isLocked && !busyElsewhere && !affordable && (
              <Button variant="secondary" disabled title={t('technology.cannot_afford')} />
            )}
            {!isMax && !isThisTechActive && !isLocked && !busyElsewhere && affordable && (
              <Button
                variant="primary"
                title={research.isPending ? t('technology.researching') : t('technology.research')}
                disabled={research.isPending}
                accessibilityLabel={t('technology.research_accessible', { technology: t(technology.name_key) })}
                onPress={() => research.mutate(technology.code)}
              />
            )}

            {research.error && (
              <Text color={theme.color.text.secondary}>{t(researchErrorKey(research.error))}</Text>
            )}
          </View>
        )}
      </View>
    </BottomSheet>
  );
}
