import { useState } from 'react';
import { router } from 'expo-router';
import { StyleSheet, TextInput, View } from 'react-native';

import { ApiError, apiRequest } from '@/api/client';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export function GuestUpgradeScreen() {
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

  async function upgrade() {
    setIsPending(true);
    setErrorKey(null);

    try {
      await apiRequest('/auth/upgrade', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      }, { authenticated: true });
      router.back();
    } catch (error) {
      setErrorKey(error instanceof ApiError ? `errors.${error.code}` : 'auth.network_error');
    } finally {
      setIsPending(false);
    }
  }

  return (
    <View style={[styles.screen, { backgroundColor: theme.color.bg.base }]}>
      <Card style={styles.card}>
        <Text variant="title">{t('auth.upgrade_title')}</Text>
        <Text color={theme.color.text.secondary}>{t('auth.upgrade_copy')}</Text>
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
          autoComplete="new-password"
          onChangeText={setPassword}
          placeholder={t('auth.password')}
          placeholderTextColor={theme.color.text.secondary}
          secureTextEntry
          style={styles.input}
          value={password}
        />
        <Button
          title={isPending ? t('auth.upgrading') : t('auth.upgrade_cta')}
          onPress={() => void upgrade()}
          disabled={isPending || email.length === 0 || password.length < 8}
        />
      </Card>
    </View>
  );
}
