import { useRef } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { router } from 'expo-router';
import type { ResourceBundle } from '@castleroyale/contracts';

import { ApiError } from '@/api/client';
import { useCityQuery } from '@/features/city/api/useCityQuery';
import { useTechnologyQuery } from '@/features/technology/api/useTechnologyQuery';
import { CategoryJumpStrip, CATEGORY_ORDER } from '@/features/technology/components/CategoryJumpStrip';
import { TechnologyCategorySection } from '@/features/technology/components/TechnologyCategorySection';
import { TechnologyDetailSheet } from '@/features/technology/components/TechnologyDetailSheet';
import { useTechnologySelectionStore } from '@/features/technology/state/technologySelectionStore';
import { Button } from '@/shared/components/Button';
import { Skeleton } from '@/shared/components/Skeleton';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

const EMPTY_RESOURCES: ResourceBundle = { food: 0, wood: 0, stone: 0, iron: 0, gold: 0 };

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

/**
 * A pushed, non-tab route — same placement as `gallery.tsx`/`onboarding.tsx`.
 * `headerShown: false` is already the global `Stack` default (`app/_layout.tsx`);
 * this screen builds its own header row, same as `military.tsx`.
 */
export default function TechnologyTreeScreen() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();

  const scrollViewRef = useRef<ScrollView>(null);
  const sectionOffsets = useRef<Partial<Record<string, number>>>({});

  const technologyQuery = useTechnologyQuery();
  const cityQuery = useCityQuery();
  const selectedTechnology = useTechnologySelectionStore((state) => state.selectedTechnology);
  const selectTechnology = useTechnologySelectionStore((state) => state.selectTechnology);
  const clearSelection = useTechnologySelectionStore((state) => state.clearSelection);

  const headerRow = (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        gap: theme.spacing.sm,
        paddingTop: insets.top + theme.spacing.lg,
        paddingHorizontal: theme.spacing.lg,
        paddingBottom: theme.spacing.sm,
      }}
    >
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={t('technology.back_accessible')}
        hitSlop={theme.spacing.md}
        onPress={() => router.back()}
        style={{
          minWidth: theme.minTouchTarget,
          minHeight: theme.minTouchTarget,
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        <MaterialCommunityIcons name="arrow-left" size={theme.spacing.xl} color={theme.color.text.primary} />
      </Pressable>
      {/* heading, not display — see 10-UI-SPEC.md's Typography section: matching
          military.tsx's display title would put five font sizes on this screen. */}
      <Text variant="heading">{t('technology.tree_title')}</Text>
    </View>
  );

  if (technologyQuery.isPending) {
    return (
      <View style={{ flex: 1, backgroundColor: theme.color.bg.base }}>
        {headerRow}
        <View style={{ gap: theme.spacing.md, padding: theme.spacing.lg }}>
          <Skeleton height={theme.spacing['2xl']} />
          <Skeleton height={theme.spacing['3xl'] * 2} />
          <Skeleton height={theme.spacing['3xl'] * 2} />
        </View>
      </View>
    );
  }

  if (technologyQuery.error || !technologyQuery.data) {
    return (
      <View style={{ flex: 1, backgroundColor: theme.color.bg.base }}>
        {headerRow}
        <View style={{ gap: theme.spacing.md, padding: theme.spacing.lg }}>
          <Text color={theme.color.text.secondary}>{t(errorKey(technologyQuery.error))}</Text>
          <Button title={t('common.retry')} variant="secondary" onPress={() => void technologyQuery.refetch()} />
        </View>
      </View>
    );
  }

  const data = technologyQuery.data;
  const technologyByCode = new Map(data.technologies.map((tech) => [tech.code, tech]));
  const selected = selectedTechnology ? (technologyByCode.get(selectedTechnology) ?? null) : null;

  return (
    <View style={{ flex: 1, backgroundColor: theme.color.bg.base }}>
      {headerRow}
      <CategoryJumpStrip
        onSelect={(category) => {
          const y = sectionOffsets.current[category];
          if (y !== undefined) scrollViewRef.current?.scrollTo({ y, animated: true });
        }}
      />

      {data.technologies.length === 0 ? (
        <View style={{ gap: theme.spacing.xs, padding: theme.spacing.lg }}>
          <Text>{t('technology.tree_empty')}</Text>
          <Text color={theme.color.text.secondary}>{t('technology.tree_empty_hint')}</Text>
        </View>
      ) : (
        <ScrollView
          ref={scrollViewRef}
          contentContainerStyle={{
            gap: theme.spacing.lg,
            padding: theme.spacing.lg,
            paddingBottom: insets.bottom + theme.spacing['2xl'],
          }}
        >
          {CATEGORY_ORDER.map((category) => (
            <TechnologyCategorySection
              key={category}
              category={category}
              technologies={data.technologies.filter((tech) => tech.category === category)}
              technologyByCode={technologyByCode}
              activeResearch={data.research}
              serverTime={data.server_time}
              onSelectTechnology={selectTechnology}
              onLayout={(event) => {
                sectionOffsets.current[category] = event.nativeEvent.layout.y;
              }}
            />
          ))}
        </ScrollView>
      )}

      <TechnologyDetailSheet
        technology={selected}
        technologyByCode={technologyByCode}
        activeResearch={data.research}
        serverTime={data.server_time}
        resources={cityQuery.data?.resources.current ?? EMPTY_RESOURCES}
        open={selected !== null}
        onClose={clearSelection}
      />
    </View>
  );
}
