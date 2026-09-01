import { useState } from 'react';
import { ScrollView, StyleSheet, TextInput, View, KeyboardAvoidingView, Platform } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { AllianceData } from '@castleroyale/contracts';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

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

export default function AllianceScreen() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [name, setName] = useState('');
  const [tag, setTag] = useState('');
  const styles = StyleSheet.create({
    content: {
      flexGrow: 1,
      gap: theme.spacing.lg,
      paddingHorizontal: theme.spacing.lg,
      paddingBottom: theme.spacing['2xl'],
    },
    heading: { gap: theme.spacing.xs },
    form: { gap: theme.spacing.md },
    field: { gap: theme.spacing.xs },
    input: {
      minHeight: theme.minTouchTarget,
      borderWidth: 1,
      borderColor: theme.color.border.subtle,
      borderRadius: theme.radius.md,
      backgroundColor: theme.color.surface.raised,
      color: theme.color.text.primary,
      paddingHorizontal: theme.spacing.md,
      paddingVertical: theme.spacing.sm,
      fontSize: theme.typography.body.size,
    },
    members: { gap: theme.spacing.sm },
    member: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: theme.spacing.md },
    memberCopy: { flex: 1, gap: theme.spacing.xs },
    loading: { gap: theme.spacing.md },
  });
  const allianceQuery = useQuery({
    queryKey: ['game', 'alliance'],
    queryFn: () => apiRequest<AllianceData>('/game/alliance', {}, { authenticated: true }),
  });
  const createAlliance = useMutation({
    mutationFn: () => apiRequest<AllianceData>(
      '/game/alliance',
      { method: 'POST', body: JSON.stringify({ name: name.trim(), tag: tag.trim() }) },
      { authenticated: true },
    ),
    onSuccess: () => {
      setName('');
      setTag('');
      queryClient.invalidateQueries({ queryKey: ['game', 'alliance'] });
    },
  });

  if (allianceQuery.isPending) {
    return (
      <View style={[styles.loading, { flex: 1, backgroundColor: theme.color.bg.base, padding: insets.top + theme.spacing.lg }] }>
        <Skeleton height={theme.spacing['2xl']} />
        <Skeleton height={theme.spacing['3xl'] * 4} />
      </View>
    );
  }

  if (allianceQuery.error || !allianceQuery.data) {
    return (
      <View style={[styles.loading, { flex: 1, backgroundColor: theme.color.bg.base, padding: insets.top + theme.spacing.lg }] }>
        <Text color={theme.color.danger}>{t(errorKey(allianceQuery.error))}</Text>
        <Button title={t('common.retry')} variant="secondary" onPress={() => void allianceQuery.refetch()} />
      </View>
    );
  }

  const alliance = allianceQuery.data;

  return (
    <KeyboardAvoidingView 
      style={{ flex: 1, backgroundColor: theme.color.bg.base }} 
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        contentContainerStyle={[styles.content, { paddingTop: insets.top + theme.spacing.lg }]}
      >
        <View style={styles.heading}>
          <Text variant="display">{t('alliance.title')}</Text>
          <Text color={theme.color.text.secondary}>{t('alliance.subtitle')}</Text>
        </View>

        {alliance.alliance ? (
          <>
            <Card>
              <View style={styles.heading}>
                <Text variant="heading">[{alliance.alliance.tag}] {alliance.alliance.name}</Text>
                <Text color={theme.color.text.secondary}>
                  {t('alliance.member_count', { count: alliance.alliance.member_count, max: alliance.alliance.max_members })}
                </Text>
              </View>
            </Card>
            <View style={styles.members}>
              <Text variant="heading">{t('alliance.roster')}</Text>
              {alliance.members.map((member) => (
                <Card key={member.player_id}>
                  <View style={styles.member}>
                    <View style={styles.memberCopy}>
                      <Text variant="label">{member.player_name}</Text>
                      <Text variant="caption" color={theme.color.text.secondary}>
                        {member.role === 'leader' ? t('alliance.leader') : t('alliance.member')}
                      </Text>
                    </View>
                    <Badge label={member.role === 'leader' ? t('alliance.leader') : t('alliance.member')} variant={member.role === 'leader' ? 'warning' : 'neutral'} />
                  </View>
                </Card>
              ))}
            </View>
          </>
        ) : (
          <Card>
            <View style={styles.form}>
              <View style={styles.heading}>
                <Text variant="heading">{t('alliance.create_title')}</Text>
                <Text color={theme.color.text.secondary}>{t('alliance.create_copy')}</Text>
              </View>
              <View style={styles.field}>
                <Text variant="label">{t('alliance.name_label')}</Text>
                <TextInput
                  value={name}
                  onChangeText={setName}
                  placeholder={t('alliance.name_placeholder')}
                  placeholderTextColor={theme.color.text.secondary}
                  style={styles.input}
                  maxLength={32}
                />
              </View>
              <View style={styles.field}>
                <Text variant="label">{t('alliance.tag_label')}</Text>
                <TextInput
                  value={tag}
                  onChangeText={(value) => setTag(value.toUpperCase())}
                  placeholder={t('alliance.tag_placeholder')}
                  placeholderTextColor={theme.color.text.secondary}
                  style={styles.input}
                  maxLength={5}
                  autoCapitalize="characters"
                />
              </View>
              <Button
                title={t('alliance.create_cta')}
                onPress={() => createAlliance.mutate()}
                disabled={name.trim().length < 3 || tag.trim().length < 2 || createAlliance.isPending}
              />
              {createAlliance.error && <Text color={theme.color.danger}>{t(errorKey(createAlliance.error))}</Text>}
            </View>
          </Card>
        )}

        <Button title={t('alliance.refresh')} variant="secondary" onPress={() => void allianceQuery.refetch()} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
