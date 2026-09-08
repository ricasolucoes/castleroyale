import { Pressable, View, type ViewStyle } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import type { ActiveResearch, Technology } from '@castleroyale/contracts';

import { Badge } from '@/shared/components/Badge';
import { Text } from '@/shared/components/Text';
import { Timer } from '@/shared/components/Timer';
import { technologyIcon } from '@/shared/components/technologyIcons';
import { progressPercent, useTickingNow } from '@/shared/utils/serverProgress';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export type TechnologyNodeCardProps = {
  technology: Technology;
  /** Resolves a prerequisite's code to its full record, for the on-card caption's name. */
  technologyByCode: Map<string, Technology>;
  /** The empire's one open research, or null. Only read when this card is in-progress. */
  activeResearch: ActiveResearch | null;
  serverTime: string;
  onPress: (code: string) => void;
};

/**
 * A 20pt corner badge, one per node state (locked/in-progress/completed).
 * Position, not colour, is what "available" is missing — see 10-UI-SPEC.md §C.
 */
function cornerBadgeStyle(fill: string, ring?: string): ViewStyle {
  return {
    position: 'absolute',
    top: -6,
    right: -6,
    width: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: fill,
    borderWidth: ring ? 1 : 0,
    borderColor: ring ?? 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
  };
}

/**
 * The tree's unit card, 96x120pt (10-UI-SPEC.md's Spacing Scale — a token
 * expression, not a raw literal). State comes straight from the server's
 * `state` field; this component never re-derives it. Every state pair differs
 * in at least one shape channel (border style, badge shape/presence, progress
 * bar presence) — never colour alone. The category icon stays `text.secondary`
 * in every state, exactly as `CitySlot`'s building glyph never changes tint.
 */
export function TechnologyNodeCard({
  technology,
  technologyByCode,
  activeResearch,
  serverTime,
  onPress,
}: TechnologyNodeCardProps) {
  const theme = useTheme();
  const { t } = useTranslation();

  const isInProgress = technology.state === 'in_progress';
  const isLocked = technology.state === 'locked';

  // Only an in-progress card needs a ticking clock; every other state renders
  // once and stays still.
  const nowMs = useTickingNow(isInProgress);
  const percent =
    isInProgress && activeResearch
      ? progressPercent(
          activeResearch.started_at,
          activeResearch.finishes_at,
          nowMs,
          nowMs - Date.parse(serverTime),
        )
      : 0;
  const finishTimestamp =
    isInProgress && activeResearch
      ? Date.parse(activeResearch.finishes_at) + (nowMs - Date.parse(serverTime))
      : null;

  const width = theme.spacing['3xl'] * 2;
  const height = theme.spacing['3xl'] * 2 + theme.spacing.xl;

  const [firstPrerequisite] = technology.prerequisites;
  const extraPrerequisites = technology.prerequisites.length - 1;
  const prerequisiteName = firstPrerequisite
    ? (technologyByCode.get(firstPrerequisite.code)?.name_key ?? firstPrerequisite.code)
    : null;
  // The prerequisite hint is moot once the technology is already running —
  // never both, per 10-UI-SPEC.md §C.
  const showPrerequisiteCaption =
    !isInProgress && technology.prerequisites.length > 0 && prerequisiteName !== null;

  return (
    <Pressable
      accessibilityRole="button"
      onPress={() => onPress(technology.code)}
      style={{
        width,
        height,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        borderStyle: isLocked ? 'dashed' : 'solid',
        borderColor: isLocked ? theme.color.border.subtle : theme.color.border.strong,
        backgroundColor: isLocked ? theme.color.bg.sunken : theme.color.surface.raised,
        overflow: 'visible',
      }}
    >
      <View
        style={{
          flex: 1,
          alignItems: 'center',
          justifyContent: 'center',
          gap: theme.spacing.xs,
          padding: theme.spacing.xs,
        }}
      >
        <MaterialCommunityIcons
          name={technologyIcon(technology.category)}
          size={theme.spacing.xl}
          color={theme.color.text.secondary}
        />
        <Text variant="label" numberOfLines={1}>
          {t(technology.name_key)}
        </Text>
        <Badge variant="neutral" label={`${technology.level}/${technology.max_level}`} />

        {isInProgress && finishTimestamp !== null && <Timer targetTimestamp={finishTimestamp} />}

        {showPrerequisiteCaption && (
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 2 }}>
            <MaterialCommunityIcons
              name="arrow-right-thin"
              size={12}
              color={theme.color.text.secondary}
            />
            <Text variant="caption" color={theme.color.text.secondary} numberOfLines={1}>
              {prerequisiteName ? t(prerequisiteName) : firstPrerequisite?.code}
              {extraPrerequisites > 0 ? ` +${extraPrerequisites}` : ''}
            </Text>
          </View>
        )}
      </View>

      {isLocked && (
        <View style={cornerBadgeStyle(theme.color.surface.overlay, theme.color.border.strong)}>
          <MaterialCommunityIcons name="lock-outline" size={12} color={theme.color.text.secondary} />
        </View>
      )}
      {isInProgress && (
        <View style={cornerBadgeStyle(theme.color.accent.bronze)}>
          <MaterialCommunityIcons name="progress-clock" size={12} color={theme.color.text.inverse} />
        </View>
      )}
      {technology.state === 'completed' && (
        <View style={cornerBadgeStyle(theme.color.success)}>
          <MaterialCommunityIcons name="check-circle" size={12} color={theme.color.text.inverse} />
        </View>
      )}

      {isInProgress && (
        <View
          style={{
            position: 'absolute',
            left: 0,
            right: 0,
            bottom: 0,
            height: theme.spacing.xs,
            borderRadius: theme.radius.full,
            overflow: 'hidden',
            backgroundColor: theme.color.border.subtle,
          }}
        >
          <View
            style={{
              width: `${percent}%`,
              height: '100%',
              backgroundColor: theme.color.accent.bronze,
            }}
          />
        </View>
      )}
    </Pressable>
  );
}
