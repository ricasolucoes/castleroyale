import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Canvas, PaintStyle, Picture, Skia } from '@shopify/react-native-skia';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';
import Animated, {
  runOnJS,
  useAnimatedReaction,
  useAnimatedStyle,
  useSharedValue,
} from 'react-native-reanimated';
import type { WorldTile } from '@castleroyale/contracts';

import { type TileBounds } from '@/features/world/rendering/cull';
import { buildMapDrawCommands, type MapMarker } from '@/features/world/rendering/commands';
import { lodForZoom } from '@/features/world/rendering/lod';
import {
  clampZoom,
  useCameraStore,
} from '@/features/world/state/cameraStore';
import { Button } from '@/shared/components/Button';
import { useTheme, type AppTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

const AnimatedView = Animated.createAnimatedComponent(View);

export type MapCanvasProps = {
  tiles: readonly WorldTile[];
  bounds: TileBounds;
  playerX: number;
  playerY: number;
  cities?: readonly MapMarker[];
  onTilePress?: (x: number, y: number) => void;
};

const TERRAIN_COLORS = {
  plains: 'success',
  forest: 'success',
  hills: 'warning',
  mountains: 'border.strong',
  river: 'accent.steel',
  road: 'accent.bronze',
} as const;

/** Resolves a dotted `TERRAIN_COLORS` path (e.g. `border.strong`) against the theme. */
function resolveTerrainColor(theme: AppTheme, terrain: WorldTile['terrain']): string {
  const segments = TERRAIN_COLORS[terrain].split('.');
  let value: unknown = theme.color;
  for (const segment of segments) {
    value = (value as Record<string, unknown>)[segment];
  }

  return value as string;
}

export function MapCanvas({ tiles, bounds, playerX, playerY, cities = [], onTilePress }: MapCanvasProps) {
  const theme = useTheme();
  const { t } = useTranslation();
  const zoom = useSharedValue(1);
  const translateX = useSharedValue(0);
  const translateY = useSharedValue(0);
  const startZoom = useSharedValue(1);
  const startTranslateX = useSharedValue(0);
  const startTranslateY = useSharedValue(0);
  const resetTo = useCameraStore((state) => state.resetTo);
  const selectCoordinate = useCameraStore((state) => state.selectCoordinate);
  const [lod, setLod] = useState(() => lodForZoom(zoom.value));
  const tileSize = theme.spacing.lg;
  const width = Math.max(1, bounds.maxX - bounds.minX + 1) * tileSize;
  const height = Math.max(1, bounds.maxY - bounds.minY + 1) * tileSize;

  // Camera stays UI-thread only; React only re-renders when a LOD tier
  // boundary is crossed, never per pan/zoom frame.
  useAnimatedReaction(
    () => lodForZoom(zoom.value),
    (next, previous) => {
      if (next !== previous) {
        runOnJS(setLod)(next);
      }
    },
  );

  const commands = useMemo(
    () => buildMapDrawCommands({ tiles, markers: cities, bounds, tileSize, lod }),
    [tiles, cities, bounds, tileSize, lod],
  );

  const picture = useMemo(() => {
    const recorder = Skia.PictureRecorder();
    const canvas = recorder.beginRecording(Skia.XYWHRect(0, 0, width, height));

    for (const command of commands) {
      if (command.kind === 'terrain') {
        const path = Skia.Path.Make();
        for (const rect of command.rects) {
          path.addRect(rect);
        }
        const paint = Skia.Paint();
        paint.setColor(Skia.Color(resolveTerrainColor(theme, command.terrain)));
        canvas.drawPath(path, paint);
        continue;
      }

      const paint = Skia.Paint();
      const color = command.marker === 'player' ? theme.color.accent.gold : theme.color.accent.steel;
      paint.setColor(Skia.Color(color));
      if (command.shape === 'ring') {
        paint.setStyle(PaintStyle.Stroke);
        paint.setStrokeWidth(1);
      }
      for (const point of command.points) {
        canvas.drawCircle(point.cx, point.cy, point.r, paint);
      }
    }

    return recorder.finishRecordingAsPicture();
  }, [commands, theme, width, height]);

  const pan = Gesture.Pan().onStart(() => {
    startTranslateX.value = translateX.value;
    startTranslateY.value = translateY.value;
  }).onUpdate((event) => {
    const minTranslateX = -width;
    const maxTranslateX = width;
    const minTranslateY = -height;
    const maxTranslateY = height;
    translateX.value = Math.max(minTranslateX, Math.min(maxTranslateX, startTranslateX.value + event.translationX));
    translateY.value = Math.max(minTranslateY, Math.min(maxTranslateY, startTranslateY.value + event.translationY));
  });
  const pinch = Gesture.Pinch().onStart(() => {
    startZoom.value = zoom.value;
  }).onUpdate((event) => {
    zoom.value = clampZoom(startZoom.value * event.scale);
  });
  const gesture = Gesture.Simultaneous(pan, pinch);
  const animatedStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: translateX.value },
      { translateY: translateY.value },
      { scale: zoom.value },
    ],
  }));
  const styles = StyleSheet.create({
    frame: {
      aspectRatio: 1,
      overflow: 'hidden',
      borderRadius: theme.radius.lg,
      borderWidth: 1,
      borderColor: theme.color.border.strong,
      backgroundColor: theme.color.bg.sunken,
    },
    canvas: { width, height, backgroundColor: theme.color.bg.sunken },
    controls: {
      position: 'absolute',
      right: theme.spacing.sm,
      bottom: theme.spacing.sm,
      minWidth: theme.minTouchTarget,
      minHeight: theme.minTouchTarget,
    },
  });

  return (
    <View style={styles.frame} accessibilityLabel={t('world.map_accessibility')}>
      <GestureDetector gesture={gesture}>
        <AnimatedView style={[styles.canvas, animatedStyle]}>
          <Canvas
            style={styles.canvas}
            onTouchEnd={(event) => {
              const { locationX, locationY } = event.nativeEvent;
              let x = Math.floor(locationX / tileSize) + bounds.minX;
              let y = Math.floor(locationY / tileSize) + bounds.minY;

              let closestDist = Infinity;
              for (const city of cities) {
                const cx = (city.x - bounds.minX + 0.5) * tileSize;
                const cy = (city.y - bounds.minY + 0.5) * tileSize;
                const dist = Math.sqrt((locationX - cx)**2 + (locationY - cy)**2);
                if (dist <= theme.minTouchTarget / 2 && dist < closestDist) {
                  closestDist = dist;
                  x = city.x;
                  y = city.y;
                }
              }

              selectCoordinate(x, y);
              onTilePress?.(x, y);
            }}
          >
            <Picture picture={picture} />
          </Canvas>
        </AnimatedView>
      </GestureDetector>
      <Pressable
        accessibilityLabel={t('world.reset_to_city')}
        onPress={() => {
          resetTo(playerX, playerY);
          translateX.value = 0;
          translateY.value = 0;
          zoom.value = 1;
          onTilePress?.(playerX, playerY);
        }}
        style={styles.controls}
      >
        <Button title={t('world.reset_to_city')} variant="secondary" />
      </Pressable>
    </View>
  );
}
