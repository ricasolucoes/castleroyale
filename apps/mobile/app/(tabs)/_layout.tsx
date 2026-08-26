import { Tabs } from 'expo-router';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export default function TabLayout() {
  const theme = useTheme();
  const { t } = useTranslation();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarStyle: {
          backgroundColor: theme.color.surface.raised,
          borderTopColor: theme.color.border.subtle,
        },
        tabBarActiveTintColor: theme.color.accent.bronze,
        tabBarInactiveTintColor: theme.color.text.secondary,
      }}
    >
      <Tabs.Screen
        name="city"
        options={{
          title: t('navigation.tabs.city'),
        }}
      />
      <Tabs.Screen
        name="world"
        options={{
          title: t('navigation.tabs.world'),
        }}
      />
      <Tabs.Screen
        name="military"
        options={{
          title: t('navigation.tabs.military'),
        }}
      />
      <Tabs.Screen
        name="alliance"
        options={{
          title: t('navigation.tabs.alliance'),
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: t('navigation.tabs.profile'),
        }}
      />
    </Tabs>
  );
}
