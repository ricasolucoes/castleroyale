import { useState } from 'react';
import { router } from 'expo-router';
import { ActivityIndicator, StyleSheet, TextInput, View } from 'react-native';
import type { AuthTokens, GameBootstrap } from '@dominion/contracts';

import { ApiError, apiRequest } from '@/api/client';
import { clearTokens, saveTokens } from '@/features/auth/SecureStorage';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export function LoginScreen() {
  const theme = useTheme();
  const { t } = useTranslation();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isPending, setIsPending] = useState(false);
  const [errorKey, setErrorKey] = useState<string | null>(null);
  const styles = StyleSheet.create({
    screen: {
      flex: 1,
      justifyContent: 'center',
      padding: theme.spacing.xl,
    },
    card: {
      gap: theme.spacing.md,
    },
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

  async function finishLogin(tokens: AuthTokens) {
    await saveTokens(tokens.access_token, tokens.refresh_token);
    await apiRequest<GameBootstrap>('/game/bootstrap', { method: 'POST' }, { authenticated: true });
    router.replace('/(tabs)/city');
  }

  async function playAsGuest() {
    setIsPending(true);
    setErrorKey(null);

    try {
      await finishLogin(await apiRequest<AuthTokens>('/auth/guest', { method: 'POST' }));
    } catch (error) {
      await clearTokens();
      setErrorKey(error instanceof ApiError ? `errors.${error.code}` : 'auth.network_error');
    } finally {
      setIsPending(false);
    }
  }

  async function signInWithEmail() {
    setIsPending(true);
    setErrorKey(null);

    try {
      const tokens = await apiRequest<AuthTokens>('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      });
      await finishLogin(tokens);
    } catch (error) {
      await clearTokens();
      setErrorKey(error instanceof ApiError ? `errors.${error.code}` : 'auth.network_error');
    } finally {
      setIsPending(false);
    }
  }

  return (
    <View style={[styles.screen, { backgroundColor: theme.color.bg.base }]}>
      <Card style={styles.card}>
        <Text variant="display">{t('auth.title')}</Text>
        <Text color={theme.color.text.secondary}>{t('auth.subtitle')}</Text>
        {errorKey && <Text color={theme.color.danger}>{t(errorKey)}</Text>}
        <TextInput
          accessibilityLabel={t('auth.email')}
          autoCapitalize="none"
          autoComplete="email"
          keyboardType="email-address"
          onChangeText={setEmail}
          placeholder={t('auth.email')}
          placeholderTextColor={theme.color.text.secondary}
          style={styles.input}
          value={email}
        />
        <TextInput
          accessibilityLabel={t('auth.password')}
          autoComplete="password"
          onChangeText={setPassword}
          placeholder={t('auth.password')}
          placeholderTextColor={theme.color.text.secondary}
          secureTextEntry
          style={styles.input}
          value={password}
        />
        <Button
          title={isPending ? t('auth.signing_in') : t('auth.email_sign_in')}
          onPress={() => void signInWithEmail()}
          disabled={isPending || email.length === 0 || password.length === 0}
        />
        <Button
          title={isPending ? t('auth.loading') : t('auth.play_guest')}
          onPress={() => void playAsGuest()}
          disabled={isPending}
          variant="secondary"
        />
        <Button
          title={t('auth.apple')}
          onPress={() => setErrorKey('auth.social_unavailable')}
          disabled={isPending}
          variant="secondary"
        />
        <Button
          title={t('auth.google')}
          onPress={() => setErrorKey('auth.social_unavailable')}
          disabled={isPending}
          variant="secondary"
        />
        {isPending && <ActivityIndicator color={theme.color.accent.bronze} />}
      </Card>
    </View>
  );
}
