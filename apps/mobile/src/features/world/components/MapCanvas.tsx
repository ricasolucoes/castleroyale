import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Canvas, PaintStyle, Picture, Skia, type SkCanvas, type SkPath } from '@shopify/react-native-skia';
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
import { clampZoom, useCameraStore } from '@/features/world/state/cameraStore';
import { selectionFeedback } from '@/shared/feedback/haptics';
import { Text } from '@/shared/components/Text';
import { useTheme, type AppTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

const AnimatedView = Animated.createAnimatedComponent(View);

export type MapCanvasProps = {
  tiles: readonly WorldTile[];
  bounds: TileBounds;
  playerX: number;
  playerY: number;
  cities?: readonly MapMarker[];
  /** Drawn as a reticle when it falls inside `bounds`. */
  selectedX?: number | null;
  selectedY?: number | null;
  onTilePress?: (x: number, y: number) => void;
};

type Rect = { x: number; y: number; width: number; height: number };

/**
 * Terrain marks.
 *
 * Each terrain draws its own figure inside the tile, so terrain survives being
 * read in greyscale, by a colourblind player, or at a glance while panning.
 * Every mark is stroked into ONE path per terrain and drawn once, which is why
 * this stays cheap enough to run across a full viewport.
 *
 * Insets are fractions of the tile, never pixels: the tile size is derived
 * from the measured frame at runtime, so a pixel inset would drift.
 */
function addTerrainMark(path: SkPath, terrain: WorldTile['terrain'], rect: Rect): void {
  const { x, y, width: w, height: h } = rect;

  switch (terrain) {
    // Plains: Organic wheat and grass tufts across fertile fields.
    case 'plains':
      // Left grass / wheat tuft
      path.moveTo(x + w * 0.25, y + h * 0.7);
      path.quadTo(x + w * 0.2, y + h * 0.45, x + w * 0.32, y + h * 0.35);
      path.moveTo(x + w * 0.25, y + h * 0.7);
      path.quadTo(x + w * 0.3, y + h * 0.5, x + w * 0.4, y + h * 0.4);
      // Right grass / wheat tuft
      path.moveTo(x + w * 0.65, y + h * 0.65);
      path.quadTo(x + w * 0.6, y + h * 0.4, x + w * 0.72, y + h * 0.3);
      path.moveTo(x + w * 0.65, y + h * 0.65);
      path.quadTo(x + w * 0.7, y + h * 0.48, x + w * 0.8, y + h * 0.38);
      // Subtle field furrow
      path.moveTo(x + w * 0.15, y + h * 0.8);
      path.lineTo(x + w * 0.85, y + h * 0.8);
      return;

    // Forest: Clustered conifer pines and dense canopies with trunks and foliage.
    case 'forest':
      // Central majestic pine
      path.moveTo(x + w * 0.5, y + h * 0.78);
      path.lineTo(x + w * 0.5, y + h * 0.86); // trunk
      path.moveTo(x + w * 0.34, y + h * 0.76);
      path.lineTo(x + w * 0.5, y + h * 0.24);
      path.lineTo(x + w * 0.66, y + h * 0.76);
      path.lineTo(x + w * 0.34, y + h * 0.76);
      // Middle foliage tier
      path.moveTo(x + w * 0.38, y + h * 0.58);
      path.lineTo(x + w * 0.5, y + h * 0.42);
      path.lineTo(x + w * 0.62, y + h * 0.58);
      // Left companion pine
      path.moveTo(x + w * 0.2, y + h * 0.8);
      path.lineTo(x + w * 0.32, y + h * 0.44);
      path.lineTo(x + w * 0.44, y + h * 0.8);
      // Right companion pine
      path.moveTo(x + w * 0.56, y + h * 0.82);
      path.lineTo(x + w * 0.68, y + h * 0.46);
      path.lineTo(x + w * 0.8, y + h * 0.82);
      return;

    // Hills: Rolling layered earthen crests with sunlit ridges.
    case 'hills':
      // Foreground rounded hill
      path.moveTo(x + w * 0.12, y + h * 0.75);
      path.quadTo(x + w * 0.38, y + h * 0.32, x + w * 0.64, y + h * 0.75);
      // Background higher crest
      path.moveTo(x + w * 0.42, y + h * 0.72);
      path.quadTo(x + w * 0.68, y + h * 0.28, x + w * 0.9, y + h * 0.72);
      // Slope contour curve
      path.moveTo(x + w * 0.24, y + h * 0.6);
      path.quadTo(x + w * 0.38, y + h * 0.48, x + w * 0.52, y + h * 0.6);
      return;

    // Mountains: Jagged dual alpine summits with snow-crested peaks and rock ridges.
    case 'mountains':
      // Primary colossal peak
      path.moveTo(x + w * 0.14, y + h * 0.82);
      path.lineTo(x + w * 0.48, y + h * 0.18);
      path.lineTo(x + w * 0.82, y + h * 0.82);
      // Center crag ridge
      path.moveTo(x + w * 0.48, y + h * 0.18);
      path.lineTo(x + w * 0.44, y + h * 0.5);
      path.lineTo(x + w * 0.54, y + h * 0.82);
      // Snowcap line
      path.moveTo(x + w * 0.38, y + h * 0.38);
      path.lineTo(x + w * 0.48, y + h * 0.44);
      path.lineTo(x + w * 0.58, y + h * 0.38);
      // Secondary rear peak
      path.moveTo(x + w * 0.56, y + h * 0.68);
      path.lineTo(x + w * 0.74, y + h * 0.32);
      path.lineTo(x + w * 0.92, y + h * 0.8);
      return;

    // River: Winding double-bank river channel with water flow currents.
    case 'river':
      // Northern bank
      path.moveTo(x, y + h * 0.38);
      path.quadTo(x + w * 0.25, y + h * 0.18, x + w * 0.5, y + h * 0.38);
      path.quadTo(x + w * 0.75, y + h * 0.58, x + w, y + h * 0.38);
      // Southern bank
      path.moveTo(x, y + h * 0.62);
      path.quadTo(x + w * 0.25, y + h * 0.42, x + w * 0.5, y + h * 0.62);
      path.quadTo(x + w * 0.75, y + h * 0.82, x + w, y + h * 0.62);
      // Water current flow ripples
      path.moveTo(x + w * 0.2, y + h * 0.32);
      path.quadTo(x + w * 0.35, y + h * 0.26, x + w * 0.48, y + h * 0.48);
      path.moveTo(x + w * 0.56, y + h * 0.54);
      path.quadTo(x + w * 0.7, y + h * 0.72, x + w * 0.84, y + h * 0.54);
      return;

    // Road: Defined cobblestone highway with stone borders.
    case 'road':
      // Left border of roadway
      path.moveTo(x, y + h * 0.36);
      path.lineTo(x + w, y + h * 0.36);
      // Right border of roadway
      path.moveTo(x, y + h * 0.64);
      path.lineTo(x + w, y + h * 0.64);
      // Cobblestone paving segments
      path.moveTo(x + w * 0.22, y + h * 0.36);
      path.lineTo(x + w * 0.22, y + h * 0.64);
      path.moveTo(x + w * 0.48, y + h * 0.36);
      path.lineTo(x + w * 0.48, y + h * 0.64);
      path.moveTo(x + w * 0.74, y + h * 0.36);
      path.lineTo(x + w * 0.74, y + h * 0.64);
      return;

    default:
      return;
  }
}

/** Draw a medieval citadel/castle for city markers. */
function addCastleFigure(path: SkPath, marker: 'player' | 'city', rect: Rect): void {
  const { x, y, width: w, height: h } = rect;
  const cx = x + w * 0.5;
  const cy = y + h * 0.5;
  const cw = w * 0.56;
  const ch = h * 0.56;
  const left = cx - cw * 0.5;
  const right = cx + cw * 0.5;
  const bottom = cy + ch * 0.45;
  const towerW = cw * 0.26;
  const towerH = ch * 0.72;
  const keepW = cw * 0.48;
  const keepH = ch * 0.92;

  // Left watchtower with battlements
  path.moveTo(left, bottom);
  path.lineTo(left, bottom - towerH);
  path.lineTo(left + towerW * 0.35, bottom - towerH);
  path.lineTo(left + towerW * 0.35, bottom - towerH + ch * 0.08);
  path.lineTo(left + towerW * 0.65, bottom - towerH + ch * 0.08);
  path.lineTo(left + towerW * 0.65, bottom - towerH);
  path.lineTo(left + towerW, bottom - towerH);
  path.lineTo(left + towerW, bottom);

  // Right watchtower with battlements
  path.moveTo(right - towerW, bottom);
  path.lineTo(right - towerW, bottom - towerH);
  path.lineTo(right - towerW + towerW * 0.35, bottom - towerH);
  path.lineTo(right - towerW + towerW * 0.35, bottom - towerH + ch * 0.08);
  path.lineTo(right - towerW + towerW * 0.65, bottom - towerH + ch * 0.08);
  path.lineTo(right - towerW + towerW * 0.65, bottom - towerH);
  path.lineTo(right, bottom - towerH);
  path.lineTo(right, bottom);

  // Central Great Keep
  const keepLeft = cx - keepW * 0.5;
  const keepRight = cx + keepW * 0.5;
  path.moveTo(keepLeft, bottom);
  path.lineTo(keepLeft, bottom - keepH);
  // Crenellations on the keep roof
  path.lineTo(keepLeft + keepW * 0.25, bottom - keepH);
  path.lineTo(keepLeft + keepW * 0.25, bottom - keepH + ch * 0.08);
  path.lineTo(keepLeft + keepW * 0.45, bottom - keepH + ch * 0.08);
  path.lineTo(keepLeft + keepW * 0.45, bottom - keepH);
  path.lineTo(keepLeft + keepW * 0.55, bottom - keepH);
  path.lineTo(keepLeft + keepW * 0.55, bottom - keepH + ch * 0.08);
  path.lineTo(keepLeft + keepW * 0.75, bottom - keepH + ch * 0.08);
  path.lineTo(keepLeft + keepW * 0.75, bottom - keepH);
  path.lineTo(keepRight, bottom - keepH);
  path.lineTo(keepRight, bottom);

  // Arched Portcullis / Gatehouse
  const gateW = cw * 0.22;
  const gateH = ch * 0.32;
  path.moveTo(cx - gateW * 0.5, bottom);
  path.lineTo(cx - gateW * 0.5, bottom - gateH * 0.6);
  path.quadTo(cx, bottom - gateH, cx + gateW * 0.5, bottom - gateH * 0.6);
  path.lineTo(cx + gateW * 0.5, bottom);

  // Royal heraldic crown crest for player capital, or faction banner for other cities
  if (marker === 'player') {
    const crownY = bottom - keepH - ch * 0.08;
    path.moveTo(cx - keepW * 0.25, crownY);
    path.lineTo(cx - keepW * 0.12, crownY - ch * 0.14);
    path.lineTo(cx, crownY - ch * 0.04);
    path.lineTo(cx + keepW * 0.12, crownY - ch * 0.14);
    path.lineTo(cx + keepW * 0.25, crownY);
    path.close();
  } else {
    const flagY = bottom - towerH;
    path.moveTo(left + towerW * 0.5, flagY);
    path.lineTo(left + towerW * 0.5, flagY - ch * 0.22);
    path.lineTo(left + towerW * 0.5 + cw * 0.18, flagY - ch * 0.14);
    path.lineTo(left + towerW * 0.5, flagY - ch * 0.06);
  }
}

/** The reticle on the selected tile: four corner brackets and heraldic highlight. */
function drawSelection(canvas: SkCanvas, theme: AppTheme, rect: Rect, strokeWidth: number): void {
  const { x, y, width: w, height: h } = rect;

  // Luminous golden highlight over the selected tile
  const fillPath = Skia.Path.Make();
  fillPath.addRect(Skia.XYWHRect(x, y, w, h));
  const fillPaint = Skia.Paint();
  fillPaint.setColor(Skia.Color('rgba(212, 160, 23, 0.18)'));
  canvas.drawPath(fillPath, fillPaint);

  const paint = Skia.Paint();
  paint.setColor(Skia.Color(theme.color.accent.gold));
  paint.setStyle(PaintStyle.Stroke);
  paint.setStrokeWidth(strokeWidth);

  const path = Skia.Path.Make();
  const arm = Math.max(2, Math.min(w, h) * 0.32);

  path.moveTo(x, y + arm);
  path.lineTo(x, y);
  path.lineTo(x + arm, y);
  path.moveTo(x + w - arm, y);
  path.lineTo(x + w, y);
  path.lineTo(x + w, y + arm);
  path.moveTo(x + w, y + h - arm);
  path.lineTo(x + w, y + h);
  path.lineTo(x + w - arm, y + h);
  path.moveTo(x + arm, y + h);
  path.lineTo(x, y + h);
  path.lineTo(x, y + h - arm);

  // Center crosshair pip
  const pip = Math.max(1.5, strokeWidth * 0.8);
  path.moveTo(x + w * 0.5 - pip, y + h * 0.5);
  path.lineTo(x + w * 0.5 + pip, y + h * 0.5);
  path.moveTo(x + w * 0.5, y + h * 0.5 - pip);
  path.lineTo(x + w * 0.5, y + h * 0.5 + pip);

  canvas.drawPath(path, paint);
}

export function MapCanvas({
  tiles,
  bounds,
  playerX,
  playerY,
  cities = [],
  selectedX = null,
  selectedY = null,
  onTilePress,
}: MapCanvasProps) {
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

  // The frame is measured, never assumed. The tile size follows from it, so
  // the viewport fills whatever space the screen gives the map instead of the
  // fixed 16pt tile that used to leave a 144pt map floating in a card.
  const [frame, setFrame] = useState({ width: 0, height: 0 });

  const tilesAcross = Math.max(1, bounds.maxX - bounds.minX + 1);
  const tilesDown = Math.max(1, bounds.maxY - bounds.minY + 1);
  const shortestFrameEdge = Math.min(frame.width, frame.height);
  const tileSize =
    shortestFrameEdge > 0
      ? shortestFrameEdge / Math.max(tilesAcross, tilesDown)
      : theme.spacing.lg;
  const width = tilesAcross * tileSize;
  const height = tilesDown * tileSize;

  // Camera stays UI-thread only; React only re-renders when a LOD tier
  // boundary is crossed, never per pan/zoom frame.
  useAnimatedReaction(
    () => {
      'worklet';
      return lodForZoom(zoom.value);
    },
    (next, previous) => {
      'worklet';
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
    // Terrain marks are detail: at the far tier they would collapse into
    // noise a pixel wide, so only the fill is recorded there.
    const drawsTerrainDetail = lod !== 'far' && tileSize >= theme.spacing.lg;
    const hairline = Math.max(1, tileSize * 0.045);

    // 1. Draw terrain base fills
    for (const command of commands) {
      if (command.kind === 'terrain') {
        const terrain = theme.terrainColors[command.terrain];
        const path = Skia.Path.Make();
        for (const rect of command.rects) {
          path.addRect(rect);
        }
        const paint = Skia.Paint();
        paint.setColor(Skia.Color(terrain.base));
        canvas.drawPath(path, paint);

        if (drawsTerrainDetail) {
          const markPath = Skia.Path.Make();
          for (const rect of command.rects) {
            addTerrainMark(markPath, command.terrain, rect);
          }
          const markPaint = Skia.Paint();
          markPaint.setColor(Skia.Color(terrain.detail));
          markPaint.setStyle(PaintStyle.Stroke);
          markPaint.setStrokeWidth(hairline);
          canvas.drawPath(markPath, markPaint);
        }
      }
    }

    // 2. Tactical 3D Tile Relief: Bevel sunlight and shadow on terrain tiles
    if (tileSize >= theme.spacing.md) {
      const bevelHighlight = Skia.Path.Make();
      const bevelShadow = Skia.Path.Make();
      for (const command of commands) {
        if (command.kind === 'terrain') {
          for (const rect of command.rects) {
            // Top and left edge highlight
            bevelHighlight.moveTo(rect.x, rect.y + rect.height);
            bevelHighlight.lineTo(rect.x, rect.y);
            bevelHighlight.lineTo(rect.x + rect.width, rect.y);
            // Bottom and right edge shadow
            bevelShadow.moveTo(rect.x + rect.width, rect.y);
            bevelShadow.lineTo(rect.x + rect.width, rect.y + rect.height);
            bevelShadow.lineTo(rect.x, rect.y + rect.height);
          }
        }
      }
      const highlightPaint = Skia.Paint();
      highlightPaint.setColor(Skia.Color('rgba(255, 255, 255, 0.12)'));
      highlightPaint.setStyle(PaintStyle.Stroke);
      highlightPaint.setStrokeWidth(Math.max(1, hairline * 0.8));
      canvas.drawPath(bevelHighlight, highlightPaint);

      const shadowPaint = Skia.Paint();
      shadowPaint.setColor(Skia.Color('rgba(0, 0, 0, 0.25)'));
      shadowPaint.setStyle(PaintStyle.Stroke);
      shadowPaint.setStrokeWidth(Math.max(1, hairline * 0.8));
      canvas.drawPath(bevelShadow, shadowPaint);
    }

    // 3. Draw markers (dots, discs, rings, and medieval citadels)
    for (const command of commands) {
      if (command.kind === 'marker') {
        const paint = Skia.Paint();
        const color = command.marker === 'player' ? theme.color.accent.gold : theme.color.accent.steel;
        paint.setColor(Skia.Color(color));
        if (command.shape === 'ring') {
          paint.setStyle(PaintStyle.Stroke);
          paint.setStrokeWidth(hairline);
        }
        for (const point of command.points) {
          canvas.drawCircle(point.cx, point.cy, point.r, paint);
        }

        // In near and mid LOD, draw a majestic medieval citadel figure for cities
        if (lod !== 'far' && tileSize >= theme.spacing.lg && command.shape !== 'ring') {
          const castlePath = Skia.Path.Make();
          for (const point of command.points) {
            addCastleFigure(castlePath, command.marker, {
              x: point.cx - tileSize * 0.5,
              y: point.cy - tileSize * 0.5,
              width: tileSize,
              height: tileSize,
            });
          }
          const castlePaint = Skia.Paint();
          castlePaint.setColor(Skia.Color(color));
          castlePaint.setStyle(PaintStyle.Stroke);
          castlePaint.setStrokeWidth(Math.max(1.2, hairline * 1.1));
          canvas.drawPath(castlePath, castlePaint);
        }
      }
    }

    // 4. Selection Reticle
    const hasSelection =
      selectedX !== null &&
      selectedY !== null &&
      selectedX >= bounds.minX &&
      selectedX <= bounds.maxX &&
      selectedY >= bounds.minY &&
      selectedY <= bounds.maxY;

    if (hasSelection) {
      drawSelection(
        canvas,
        theme,
        {
          x: (selectedX - bounds.minX) * tileSize,
          y: (selectedY - bounds.minY) * tileSize,
          width: tileSize,
          height: tileSize,
        },
        Math.max(1.5, hairline * 1.5),
      );
    }

    const nextPicture = recorder.finishRecordingAsPicture();
    recorder.dispose();
    return nextPicture;
  }, [commands, theme, width, height, lod, tileSize, selectedX, selectedY, bounds]);

  const pan = Gesture.Pan()
    .onStart(() => {
      'worklet';
      startTranslateX.value = translateX.value;
      startTranslateY.value = translateY.value;
    })
    .onUpdate((event) => {
      'worklet';
      // Generous exploration margin so panning is natural and fluid across the map
      const margin = Math.max(tileSize * 3, shortestFrameEdge * 0.3);
      const overscrollX = Math.max(margin, (width * zoom.value - frame.width) / 2 + margin);
      const overscrollY = Math.max(margin, (height * zoom.value - frame.height) / 2 + margin);
      translateX.value = Math.max(
        -overscrollX,
        Math.min(overscrollX, startTranslateX.value + event.translationX),
      );
      translateY.value = Math.max(
        -overscrollY,
        Math.min(overscrollY, startTranslateY.value + event.translationY),
      );
    });
  const pinch = Gesture.Pinch()
    .onStart(() => {
      'worklet';
      startZoom.value = zoom.value;
    })
    .onUpdate((event) => {
      'worklet';
      zoom.value = clampZoom(startZoom.value * event.scale);
    });
  const gesture = Gesture.Simultaneous(pan, pinch);
  const animatedStyle = useAnimatedStyle(() => {
    'worklet';
    return {
      transform: [
        { translateX: translateX.value },
        { translateY: translateY.value },
        { scale: zoom.value },
      ],
    };
  });
  const styles = StyleSheet.create({
    frame: {
      flex: 1,
      overflow: 'hidden',
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: theme.color.bg.sunken,
    },
    canvas: { width, height },
    controls: {
      position: 'absolute',
      right: theme.spacing.lg,
      bottom: theme.spacing.lg,
      minWidth: theme.minTouchTarget,
      minHeight: theme.minTouchTarget,
      paddingHorizontal: theme.spacing.md,
      alignItems: 'center',
      justifyContent: 'center',
      borderRadius: theme.radius.full,
      borderWidth: 1,
      borderColor: theme.color.hud.edge,
      backgroundColor: theme.color.hud.panel,
    },
  });

  return (
    <View
      style={styles.frame}
      accessibilityLabel={t('world.map_accessibility')}
      onLayout={(event) => {
        const { width: layoutWidth, height: layoutHeight } = event.nativeEvent.layout;
        setFrame((current) =>
          current.width === layoutWidth && current.height === layoutHeight
            ? current
            : { width: layoutWidth, height: layoutHeight },
        );
      }}
    >
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
                const dist = Math.sqrt((locationX - cx) ** 2 + (locationY - cy) ** 2);
                if (dist <= theme.minTouchTarget / 2 && dist < closestDist) {
                  closestDist = dist;
                  x = city.x;
                  y = city.y;
                }
              }

              selectionFeedback();
              selectCoordinate(x, y);
              onTilePress?.(x, y);
            }}
          >
            <Picture picture={picture} />
          </Canvas>
        </AnimatedView>
      </GestureDetector>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={t('world.reset_to_city')}
        onPress={() => {
          selectionFeedback();
          resetTo(playerX, playerY);
          translateX.value = 0;
          translateY.value = 0;
          zoom.value = 1;
          onTilePress?.(playerX, playerY);
        }}
        style={({ pressed }) => [styles.controls, { opacity: pressed ? 0.75 : 1 }]}
      >
        <Text variant="label" color={theme.color.accent.bronze}>
          {t('world.reset_to_city')}
        </Text>
      </Pressable>
    </View>
  );
}
