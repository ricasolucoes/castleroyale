import { useEffect, useRef } from 'react';
import { Animated, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useQuery } from '@tanstack/react-query';
import type { MilitaryData } from '@dominion/contracts';

import { ApiError, apiRequest } from '@/api/client';
import { Badge } from '@/shared/components/Badge';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Skeleton } from '@/shared/components/Skeleton';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

function errorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.network';
}

function classKey(unitClass: string): string {
  return `military.class_${unitClass}`;
}

export default function MilitaryScreen() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();
  const pulse = useRef(new Animated.Value(0.92)).current;
  const styles = StyleSheet.create({
    content: {
      flexGrow: 1,
      gap: theme.spacing.lg,
      paddingHorizontal: theme.spacing.lg,
      paddingBottom: theme.spacing['2xl'],
    },
    heading: { gap: theme.spacing.xs },
    powerCard: { gap: theme.spacing.sm },
    powerValue: { flexDirection: 'row', alignItems: 'flex-end', gap: theme.spacing.sm },
    units: { gap: theme.spacing.sm },
    unitHeader: { flexDirection: 'row', justifyContent: 'space-between', gap: theme.spacing.md },
    unitCopy: { flex: 1, gap: theme.spacing.xs },
    loading: { gap: theme.spacing.md },
  });
  const militaryQuery = useQuery({
    queryKey: ['game', 'military'],
    queryFn: () => apiRequest<MilitaryData>('/game/military', {}, { authenticated: true }),
  });

  useEffect(() => {
    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: theme.motion.slow, useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 0.92, duration: theme.motion.slow, useNativeDriver: true }),
      ]),
    );
    animation.start();

    return () => animation.stop();
  }, [pulse, theme.motion.slow]);

  if (militaryQuery.isPending) {
    return (
      <View style={[styles.loading, { flex: 1, backgroundColor: theme.color.bg.base, padding: insets.top + theme.spacing.lg }] }>
        <Skeleton height={theme.spacing['2xl']} />
        <Skeleton height={theme.spacing['3xl'] * 2} />
        <Skeleton height={theme.spacing['3xl']} />
      </View>
    );
  }

  if (militaryQuery.error || !militaryQuery.data) {
    return (
      <View style={[styles.loading, { flex: 1, backgroundColor: theme.color.bg.base, padding: insets.top + theme.spacing.lg }] }>
        <Text color={theme.color.danger}>{t(errorKey(militaryQuery.error))}</Text>
        <Button title={t('common.retry')} variant="secondary" onPress={() => void militaryQuery.refetch()} />
      </View>
    );
  }

  const military = militaryQuery.data;

  return (
    <ScrollView
      contentContainerStyle={[styles.content, { backgroundColor: theme.color.bg.base, paddingTop: insets.top + theme.spacing.lg }]}
    >
      <View style={styles.heading}>
        <Text variant="display">{t('military.title')}</Text>
        <Text color={theme.color.text.secondary}>{t('military.subtitle')}</Text>
      </View>

      <Animated.View style={{ transform: [{ scale: pulse }] }}>
        <Card style={styles.powerCard}>
          <Text variant="label" color={theme.color.text.secondary}>{t('military.power')}</Text>
          <View style={styles.powerValue}>
            <Text variant="display" color={theme.color.accent.gold}>{military.total_power}</Text>
            <Badge label={t('military.server_sync')} variant="success" />
          </View>
          <Text variant="caption" color={theme.color.text.secondary}>
            {t('military.garrison', { city: t(military.city.name_key) })}
          </Text>
        </Card>
      </Animated.View>

      <View style={styles.units}>
        <Text variant="heading">{t('military.units')}</Text>
        {military.units.map((unit) => (
          <Card key={unit.code}>
            <View style={styles.unitHeader}>
              <View style={styles.unitCopy}>
                <Text variant="heading">{t(unit.name_key)}</Text>
                <Text variant="caption" color={theme.color.text.secondary}>{t(classKey(unit.unit_class))}</Text>
              </View>
              <Badge label={t('military.unit_count', { quantity: unit.quantity })} variant="neutral" />
            </View>
            <Text variant="caption" color={theme.color.text.secondary}>
              {t('military.unit_power', { power: unit.total_power })}
            </Text>
          </Card>
        ))}
      </View>

      <Card>
        <View style={styles.heading}>
          <Text variant="heading">{t('military.training_next')}</Text>
          <Text color={theme.color.text.secondary}>{t('military.training_next_copy')}</Text>
        </View>
      </Card>

      <Button title={t('military.refresh')} variant="secondary" onPress={() => void militaryQuery.refetch()} />
    </ScrollView>
  );
}
