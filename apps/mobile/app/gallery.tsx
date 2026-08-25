import React, { useRef } from 'react';
import { ScrollView, View, StyleSheet } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '@/theme';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Panel } from '@/shared/components/Panel';
import { Badge } from '@/shared/components/Badge';
import { Skeleton } from '@/shared/components/Skeleton';
import { ResourceCounter } from '@/shared/components/ResourceCounter';
import { Timer } from '@/shared/components/Timer';
import { BottomSheet } from '@/shared/components/BottomSheet';
import { Text } from '@/shared/components/Text';
import { Box } from '@/shared/components/Box';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';

export default function GalleryScreen() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const bottomSheetRef = useRef<GorhomBottomSheet>(null);

  const styles = StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.color.bg.base,
    },
    content: {
      paddingHorizontal: theme.spacing.md,
      paddingTop: insets.top + theme.spacing.lg,
      paddingBottom: insets.bottom + 100, // extra padding for bottom sheet
      gap: theme.spacing.xl,
    },
    section: {
      gap: theme.spacing.md,
    },
    row: {
      flexDirection: 'row',
      gap: theme.spacing.md,
      flexWrap: 'wrap',
    },
  });

  return (
    <View style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>
        <Text variant="display">Developer Gallery</Text>
        
        <Box backgroundColor={theme.color.surface.raised} p="md" borderRadius="md" style={styles.section}>
          <Text variant="body">Buttons</Text>
          <View style={styles.row}>
            <Button title="Primary" onPress={() => {}} variant="primary" />
            <Button title="Secondary" onPress={() => {}} variant="secondary" />
            <Button title="Danger" onPress={() => {}} variant="danger" />
            <Button title="Disabled" onPress={() => {}} disabled />
          </View>
        </Box>

        <View style={styles.section}>
          <Text variant="body">Cards & Panels</Text>
          <Card>
            <Text variant="body">Card Title</Text>
            <Text variant="body" color={theme.color.text.secondary}>Subtitle goes here</Text>
            <Box mt="sm">
              <Text variant="body">This is the card content area.</Text>
            </Box>
          </Card>
          <Panel>
            <Text variant="body">This is a generic panel component used for grouping content.</Text>
          </Panel>
        </View>

        <View style={styles.section}>
          <Text variant="body">Badges</Text>
          <View style={styles.row}>
            <Badge label="Neutral" variant="neutral" />
            <Badge label="Success" variant="success" />
            <Badge label="Warning" variant="warning" />
            <Badge label="Danger" variant="danger" />
          </View>
        </View>

        <View style={styles.section}>
          <Text variant="body">Skeletons</Text>
          <View style={[styles.row, { alignItems: 'center' }]}>
            <Skeleton width={40} height={40} borderRadius={20} />
            <View style={{ gap: 8, flex: 1 }}>
              <Skeleton width="100%" height={20} />
              <Skeleton width="80%" height={20} />
            </View>
          </View>
        </View>

        <View style={styles.section}>
          <Text variant="body">Domain Specific</Text>
          <View style={styles.row}>
            <ResourceCounter resource="gold" amount={1500} />
            <ResourceCounter resource="wood" amount={200} />
          </View>
          <Timer
            targetTimestamp={Date.now() + 1000 * 60 * 5}
          />
        </View>

        <View style={styles.section}>
          <Text variant="body">Bottom Sheet</Text>
          <Button title="Open Bottom Sheet" onPress={() => bottomSheetRef.current?.expand()} variant="secondary" />
        </View>
      </ScrollView>

      <BottomSheet ref={bottomSheetRef} snapPoints={['25%', '50%']} enablePanDownToClose index={-1}>
        <Box p="lg" style={{ gap: 16 }}>
          <Text variant="body">Bottom Sheet Content</Text>
          <Text variant="body">This sheet can contain complex interactive elements.</Text>
          <Button title="Close" onPress={() => bottomSheetRef.current?.close()} variant="primary" />
        </Box>
      </BottomSheet>
    </View>
  );
}
