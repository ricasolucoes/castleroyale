import { Tabs } from 'expo-router';
import { useTheme } from '@/theme';

export default function TabLayout() {
  const theme = useTheme();

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
          title: 'City',
        }}
      />
      <Tabs.Screen
        name="world"
        options={{
          title: 'World',
        }}
      />
      <Tabs.Screen
        name="military"
        options={{
          title: 'Military',
        }}
      />
      <Tabs.Screen
        name="alliance"
        options={{
          title: 'Alliance',
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profile',
        }}
      />
    </Tabs>
  );
}
