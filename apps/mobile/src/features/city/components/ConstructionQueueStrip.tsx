import { useEffect, useState } from 'react';
import { Pressable, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import type { Construction } from '@castleroyale/contracts';

import { buildingIcon } from '@/shared/components/buildingIcons';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export type ConstructionQueueStripProps = {
  constructions: Construction[];
  queueLimit: number;
  /** Resolves a building_code to the plot holding it, or null. Supplied by CityScene. */
  slotForBuildingCode: (code: string) => string | null;
  /** Resolves a building_code to its name_key, for the pip's accessibility label. */
  nameKeyForBuildingCode: (code: string) => string | null;
  serverTime: string;
  onSelectSlot: (slot: string) => void;
};

function clampPercent(value: number): number {
  if (Number.isNaN(value)) return 0;
  return Math.min(100, Math.max(0, value));
}

/**
 * Occupancy of the city's build queue at a glance, and a way back to whatever is
 * building. The grid's per-tile `Timer` already gives exact remaining time — the
 * strip's job is "how full is the queue, and take me there", so its pips carry a
 * coarse progress bar rather than a second countdown.
 */
export function ConstructionQueueStrip({
  constructions,
  queueLimit,
  slotForBuildingCode,
  nameKeyForBuildingCode,
  serverTime,
  onSelectSlot,
}: ConstructionQueueStripProps) {
  const theme = useTheme();
  const { t } = useTranslation();

  // Re-render at the same 1 Hz cadence, and with the same "plain update, no
  // animated roll" policy, that `Timer.tsx` already uses.
  const [nowMs, setNowMs] = useState(() => Date.now());
  useEffect(() => {
    const interval = setInterval(() => setNowMs(Date.now()), 1000);
    return () => clearInterval(interval);
  }, []);

  // Always true today. It guards a future world configured without a build
  // queue rather than assuming one can never exist.
  if (queueLimit <= 0) return null;

  // The device clock is never the source of the deadline — the same skew
  // correction `CityScene` applies to every tile's Timer.
  const clockSkewMs = nowMs - Date.parse(serverTime);

  return (
    <View style={{ gap: theme.spacing.xs }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
        <Text variant="label">{t('city.queue_title')}</Text>
        <Text variant="caption" color={theme.color.text.secondary}>
          {t('city.queue_slots', { active: constructions.length, max: queueLimit })}
        </Text>
      </View>

      <View
        accessibilityLabel={t('city.queue_accessibility', {
          active: constructions.length,
          max: queueLimit,
        })}
        style={{ flexDirection: 'row', flexWrap: 'wrap', gap: theme.spacing.xs }}
      >
        {Array.from({ length: queueLimit }, (_, index) => {
          const construction = constructions[index];

          if (construction === undefined) {
            // The container already announces "N of M slots in use"; four more
            // VoiceOver nodes saying "queue slot available" would be noise.
            return (
              <View
                key={`empty-${index}`}
                accessibilityElementsHidden
                importantForAccessibility="no-hide-descendants"
                style={{
                  width: theme.minTouchTarget,
                  height: theme.minTouchTarget,
                  backgroundColor: theme.color.bg.sunken,
                  borderWidth: 1,
                  borderStyle: 'dashed',
                  borderColor: theme.color.border.subtle,
                  borderRadius: theme.radius.md,
                }}
              />
            );
          }

          const startedMs = Date.parse(construction.started_at) + clockSkewMs;
          const finishesMs = Date.parse(construction.finishes_at) + clockSkewMs;
          const percent =
            finishesMs === startedMs
              ? 100
              : clampPercent(((nowMs - startedMs) / (finishesMs - startedMs)) * 100);

          const nameKey = nameKeyForBuildingCode(construction.building_code);

          return (
            <Pressable
              key={construction.building_code}
              accessibilityRole="button"
              accessibilityLabel={t('city.queue_slot_accessible', {
                building: nameKey === null ? construction.building_code : t(nameKey),
              })}
              onPress={() => {
                const slot = slotForBuildingCode(construction.building_code);
                if (slot !== null) onSelectSlot(slot);
              }}
              style={{
                width: theme.minTouchTarget,
                height: theme.minTouchTarget,
                backgroundColor: theme.color.surface.raised,
                borderWidth: 1,
                borderColor: theme.color.border.strong,
                borderRadius: theme.radius.md,
                alignItems: 'center',
                justifyContent: 'center',
                overflow: 'hidden',
              }}
            >
              <MaterialCommunityIcons
                name={buildingIcon(construction.building_code)}
                size={theme.spacing.lg}
                color={theme.color.text.secondary}
              />
              <View
                style={{
                  position: 'absolute',
                  left: 0,
                  right: 0,
                  bottom: 0,
                  height: theme.spacing.xs,
                  borderRadius: theme.radius.full,
                  backgroundColor: theme.color.border.subtle,
                }}
              >
                <View
                  style={{
                    width: `${percent}%`,
                    height: '100%',
                    borderRadius: theme.radius.full,
                    backgroundColor: theme.color.accent.bronze,
                  }}
                />
              </View>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}
