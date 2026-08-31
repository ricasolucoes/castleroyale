import { useState } from 'react';
import { router } from 'expo-router';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, TextInput, View, KeyboardAvoidingView, Platform } from 'react-native';
import type { GameBootstrap, WorldList, WorldOption } from '@dominion/contracts';

import { ApiError, apiRequest } from '@/api/client';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';
import { useQuery } from '@tanstack/react-query';

export function OnboardingScreen() {
  const theme = useTheme();
  const { t } = useTranslation();
  const [selectedWorld, setSelectedWorld] = useState<WorldOption | null>(null);
  const [name, setName] = useState('');
  const [isEntering, setIsEntering] = useState(false);
  const [errorKey, setErrorKey] = useState<string | null>(null);
  const worldsQuery = useQuery({
    queryKey: ['game', 'worlds'],
    queryFn: () => apiRequest<WorldList>('/game/worlds', {}, { authenticated: true }),
  });

  const styles = StyleSheet.create({
    screen: { flex: 1, padding: theme.spacing.xl },
    content: { gap: theme.spacing.md, paddingBottom: theme.spacing.xl },
    worlds: { gap: theme.spacing.sm },
    worldCard: { gap: theme.spacing.xs },
    selected: { borderColor: theme.color.accent.bronze, borderWidth: 2 },
    input: {
      minHeight: theme.minTouchTarget,
      borderWidth: 1,
      borderColor: theme.color.border.subtle,
      borderRadius: theme.radius.md,
      paddingHorizontal: theme.spacing.md,
      color: theme.color.text.primary,
      backgroundColor: theme.color.bg.base,
      fontSize: theme.typography.body.size,
    },
  });

  async function enterWorld() {
    if (!selectedWorld || name.trim().length === 0) return;

    setIsEntering(true);
    setErrorKey(null);
    try {
      await apiRequest<GameBootstrap>(`/game/worlds/${encodeURIComponent(selectedWorld.id)}/select`, {
        method: 'POST',
        body: JSON.stringify({ world_id: selectedWorld.id, name }),
      }, { authenticated: true });
      router.replace('/(tabs)/city');
    } catch (error) {
      setErrorKey(error instanceof ApiError ? `errors.${error.code}` : 'auth.network_error');
    } finally {
      setIsEntering(false);
    }
  }

  function statusLabel(world: WorldOption): string {
    return t(`auth.world_${world.status}`);
  }

  if (worldsQuery.isPending) {
    return <ActivityIndicator style={styles.screen} color={theme.color.accent.bronze} />;
  }

  if (worldsQuery.error || !worldsQuery.data) {
    return (
      <View style={[styles.screen, { backgroundColor: theme.color.bg.base, justifyContent: 'center' }]}>
        <Card style={styles.content}>
          <Text color={theme.color.danger}>{t('auth.network_error')}</Text>
          <Button title={t('common.retry')} variant="secondary" onPress={() => void worldsQuery.refetch()} />
        </Card>
      </View>
    );
  }

  return (
    <KeyboardAvoidingView 
      style={{ flex: 1, backgroundColor: theme.color.bg.base }} 
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
        <Text variant="display">{t('auth.onboarding_title')}</Text>
        <Text color={theme.color.text.secondary}>{t('auth.onboarding_subtitle')}</Text>
        {errorKey && <Text color={theme.color.danger}>{t(errorKey)}</Text>}
        <TextInput
          accessibilityLabel={t('auth.player_name')}
          autoCapitalize="words"
          onChangeText={setName}
          placeholder={t('auth.player_name_placeholder')}
          placeholderTextColor={theme.color.text.secondary}
          style={styles.input}
          value={name}
        />
        <View style={styles.worlds}>
          {worldsQuery.data.worlds.map((world) => {
            const unavailable = world.status !== 'open';
            return (
              <Pressable
                accessibilityRole="button"
                accessibilityState={{ disabled: unavailable, selected: selectedWorld?.id === world.id }}
                disabled={unavailable}
                key={world.id}
                onPress={() => setSelectedWorld(world)}
              >
                <Card
                  style={{
                    ...styles.worldCard,
                    ...(selectedWorld?.id === world.id ? styles.selected : {}),
                    ...(unavailable ? { opacity: 0.55 } : {}),
                  }}
                >
                  <Text variant="heading">{world.name}</Text>
                  <Text color={theme.color.text.secondary}>{world.code}</Text>
                  <Text>{t('auth.world_population', { population: world.population, capacity: world.capacity })}</Text>
                  <Text color={unavailable ? theme.color.danger : theme.color.success}>{statusLabel(world)}</Text>
                </Card>
              </Pressable>
            );
          })}
        </View>
        <Button
          title={isEntering ? t('auth.entering_world') : t('auth.enter_world')}
          disabled={isEntering || selectedWorld === null || name.trim().length === 0}
          onPress={() => void enterWorld()}
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
