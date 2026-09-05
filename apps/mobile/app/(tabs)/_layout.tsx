import { Tabs } from 'expo-router';
import { View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { ResourceBar } from '@/features/economy/components/ResourceBar';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

export default function TabLayout() {
  const theme = useTheme();
  const { t } = useTranslation();

  return (
    <View style={{ flex: 1 }}>
      <ResourceBar />
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
            tabBarIcon: ({ color, size, focused }) => (
              <MaterialCommunityIcons
                name={focused ? 'home-city' : 'home-city-outline'}
                size={size}
                color={color}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="world"
          options={{
            title: t('navigation.tabs.world'),
            tabBarIcon: ({ color, size, focused }) => (
              <MaterialCommunityIcons
                name={focused ? 'earth' : 'earth-arrow-right'}
                size={size}
                color={color}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="military"
          options={{
            title: t('navigation.tabs.military'),
            tabBarIcon: ({ color, size, focused }) => (
              <MaterialCommunityIcons
                name={focused ? 'shield-sword' : 'shield-outline'}
                size={size}
                color={color}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="alliance"
          options={{
            title: t('navigation.tabs.alliance'),
            tabBarIcon: ({ color, size, focused }) => (
              <MaterialCommunityIcons
                name={focused ? 'account-group' : 'account-group-outline'}
                size={size}
                color={color}
              />
            ),
          }}
        />
        <Tabs.Screen
          name="profile"
          options={{
            title: t('navigation.tabs.profile'),
            tabBarIcon: ({ color, size, focused }) => (
              <MaterialCommunityIcons
                name={focused ? 'account-circle' : 'account-circle-outline'}
                size={size}
                color={color}
              />
            ),
          }}
        />
      </Tabs>
    </View>
  );
}
