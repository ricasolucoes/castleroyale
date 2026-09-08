import { ScrollView, View, type LayoutChangeEvent } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import type { ActiveResearch, Technology } from '@castleroyale/contracts';

import { Text } from '@/shared/components/Text';
import { technologyIcon } from '@/shared/components/technologyIcons';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

import { TechnologyNodeCard } from './TechnologyNodeCard';

export type TechnologyCategorySectionProps = {
  category: string;
  /** Already filtered to this category by the caller. */
  technologies: Technology[];
  technologyByCode: Map<string, Technology>;
  activeResearch: ActiveResearch | null;
  serverTime: string;
  onSelectTechnology: (code: string) => void;
  onLayout: (event: LayoutChangeEvent) => void;
};

/**
 * One category: a heading, then one horizontal lane per non-empty `tier`
 * present in this category, ascending, skipping any tier with no nodes here
 * (10-UI-SPEC.md's Military example — tier 1 skipped silently). `tier` is the
 * server's own topological-depth field; this never re-derives graph structure.
 * A category with zero technologies in the current dataset renders nothing.
 */
export function TechnologyCategorySection({
  category,
  technologies,
  technologyByCode,
  activeResearch,
  serverTime,
  onSelectTechnology,
  onLayout,
}: TechnologyCategorySectionProps) {
  const theme = useTheme();
  const { t } = useTranslation();

  if (technologies.length === 0) return null;

  const tiers = Array.from(new Set(technologies.map((tech) => tech.tier))).sort((a, b) => a - b);

  return (
    <View onLayout={onLayout} style={{ gap: theme.spacing.sm }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: theme.spacing.xs }}>
        <MaterialCommunityIcons
          name={technologyIcon(category)}
          size={theme.spacing.xl}
          color={theme.color.text.secondary}
        />
        <Text variant="heading">{t(`technology.category_${category}`)}</Text>
      </View>

      {tiers.map((tier) => (
        <ScrollView
          key={tier}
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={{ gap: theme.spacing.sm }}
        >
          {technologies
            .filter((tech) => tech.tier === tier)
            .map((tech) => (
              <TechnologyNodeCard
                key={tech.code}
                technology={tech}
                technologyByCode={technologyByCode}
                activeResearch={activeResearch}
                serverTime={serverTime}
                onPress={onSelectTechnology}
              />
            ))}
        </ScrollView>
      ))}
    </View>
  );
}
