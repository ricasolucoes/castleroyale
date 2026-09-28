import { useEffect, useRef, useState } from 'react';
import {
  Animated,
  Image,
  Pressable,
  StyleSheet,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';

import { selectionFeedback } from '@/shared/feedback/haptics';
import { Button } from '@/shared/components/Button';
import { Text } from '@/shared/components/Text';
import { useTheme } from '@/theme';
import { useTranslation } from '@/i18n/useTranslation';

type StoryChapter = {
  id: number;
  titleKey: string;
  subtitleKey: string;
  bodyKey: string;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  artwork: any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  videoSource?: any;
};

const CHAPTERS: StoryChapter[] = [
  {
    id: 1,
    titleKey: 'intro.chapter1_title',
    subtitleKey: 'intro.chapter1_subtitle',
    bodyKey: 'intro.chapter1_body',
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    artwork: require('../../../../assets/game/castle-hero.jpg'),
  },
  {
    id: 2,
    titleKey: 'intro.chapter2_title',
    subtitleKey: 'intro.chapter2_subtitle',
    bodyKey: 'intro.chapter2_body',
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    artwork: require('../../../../assets/game/village-house.jpg'),
  },
  {
    id: 3,
    titleKey: 'intro.chapter3_title',
    subtitleKey: 'intro.chapter3_subtitle',
    bodyKey: 'intro.chapter3_body',
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    artwork: require('../../../../assets/game/royal-guard.png'),
  },
  {
    id: 4,
    titleKey: 'intro.chapter4_title',
    subtitleKey: 'intro.chapter4_subtitle',
    bodyKey: 'intro.chapter4_body',
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    artwork: require('../../../../assets/game/legendary-sword.png'),
  },
];

export type IntroCinematicScreenProps = {
  onComplete?: () => void;
};

export function IntroCinematicScreen({ onComplete }: IntroCinematicScreenProps) {
  const theme = useTheme();
  const { t } = useTranslation();
  const [currentChapterIndex, setCurrentChapterIndex] = useState(0);

  // Ken Burns subtle pan/zoom
  const zoomAnim = useRef(new Animated.Value(1)).current;
  const fadeAnim = useRef(new Animated.Value(0)).current;

  const currentChapter = CHAPTERS[currentChapterIndex]!;
  const isLastChapter = currentChapterIndex === CHAPTERS.length - 1;

  useEffect(() => {
    fadeAnim.setValue(0);
    zoomAnim.setValue(1);

    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: theme.motion.base,
        useNativeDriver: true,
      }),
      Animated.timing(zoomAnim, {
        toValue: 1.08,
        duration: 8000,
        useNativeDriver: true,
      }),
    ]).start();
  }, [currentChapterIndex, fadeAnim, zoomAnim, theme.motion.base]);

  function handleFinish() {
    selectionFeedback();
    if (onComplete) {
      onComplete();
      return;
    }
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace('/auth/login');
    }
  }

  function handleNext() {
    selectionFeedback();
    if (isLastChapter) {
      handleFinish();
    } else {
      setCurrentChapterIndex((prev) => Math.min(prev + 1, CHAPTERS.length - 1));
    }
  }

  function handlePrevious() {
    if (currentChapterIndex > 0) {
      selectionFeedback();
      setCurrentChapterIndex((prev) => prev - 1);
    }
  }

  const styles = StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: '#090B0E',
    },
    artworkContainer: {
      ...StyleSheet.absoluteFill,
      overflow: 'hidden',
    },
    artwork: {
      width: '100%',
      height: '100%',
    },
    vignetteOverlay: {
      ...StyleSheet.absoluteFill,
      backgroundColor: 'rgba(9, 11, 14, 0.45)',
    },
    gradientBottom: {
      position: 'absolute',
      left: 0,
      right: 0,
      bottom: 0,
      height: '65%',
      backgroundColor: 'rgba(9, 11, 14, 0.88)',
    },
    safeArea: {
      flex: 1,
      justifyContent: 'space-between',
    },
    header: {
      paddingHorizontal: theme.spacing.lg,
      paddingTop: theme.spacing.md,
      gap: theme.spacing.md,
    },
    progressRow: {
      flexDirection: 'row',
      gap: theme.spacing.xs,
    },
    progressBarBackground: {
      flex: 1,
      height: 4,
      borderRadius: 2,
      backgroundColor: 'rgba(255, 255, 255, 0.2)',
      overflow: 'hidden',
    },
    progressBarFill: {
      height: '100%',
      borderRadius: 2,
      backgroundColor: theme.color.accent.gold,
    },
    headerNav: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
    },
    skipText: {
      paddingVertical: theme.spacing.xs,
      paddingHorizontal: theme.spacing.sm,
    },
    touchSteppingArea: {
      flex: 1,
      flexDirection: 'row',
    },
    touchStepLeft: {
      flex: 1,
    },
    touchStepRight: {
      flex: 2,
    },
    contentBox: {
      paddingHorizontal: theme.spacing.xl,
      paddingBottom: theme.spacing.xl,
      gap: theme.spacing.md,
    },
    chapterTag: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: theme.spacing.xs,
    },
    bodyText: {
      lineHeight: theme.typography.body.size * 1.5,
    },
    footerControls: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: theme.spacing.md,
      marginTop: theme.spacing.sm,
    },
    prevButton: {
      minWidth: 100,
    },
    nextButton: {
      flex: 1,
    },
  });

  return (
    <View style={styles.container} testID="intro-cinematic-screen">
      {/* Visual Background with Zoom */}
      <View style={styles.artworkContainer}>
        <Animated.View
          style={[
            StyleSheet.absoluteFill,
            {
              transform: [{ scale: zoomAnim }],
              opacity: fadeAnim,
            },
          ]}
        >
          <Image
            source={currentChapter.artwork}
            style={styles.artwork}
            resizeMode="cover"
            accessibilityLabel={t(currentChapter.titleKey)}
          />
        </Animated.View>
        <View style={styles.vignetteOverlay} />
        <View style={styles.gradientBottom} />
      </View>

      <SafeAreaView style={styles.safeArea}>
        {/* Progress Bar & Skip */}
        <View style={styles.header}>
          <View style={styles.progressRow} accessibilityRole="progressbar">
            {CHAPTERS.map((ch, idx) => (
              <View key={ch.id} style={styles.progressBarBackground}>
                <View
                  style={[
                    styles.progressBarFill,
                    {
                      width:
                        idx < currentChapterIndex
                          ? '100%'
                          : idx === currentChapterIndex
                            ? '100%'
                            : '0%',
                      opacity: idx <= currentChapterIndex ? 1 : 0,
                    },
                  ]}
                />
              </View>
            ))}
          </View>

          <View style={styles.headerNav}>
            <Text variant="caption" color={theme.color.accent.gold}>
              {t('intro.chapter_count', {
                current: currentChapterIndex + 1,
                total: CHAPTERS.length,
              })}
            </Text>

            <Pressable
              accessibilityRole="button"
              accessibilityLabel={t('intro.skip')}
              onPress={handleFinish}
              style={styles.skipText}
              hitSlop={12}
            >
              <Text variant="label" color={theme.color.text.secondary}>
                {t('intro.skip')} ✕
              </Text>
            </Pressable>
          </View>
        </View>

        {/* Tap areas for navigation */}
        <View style={styles.touchSteppingArea}>
          <Pressable
            style={styles.touchStepLeft}
            onPress={handlePrevious}
            accessibilityLabel={t('intro.previous')}
          />
          <Pressable
            style={styles.touchStepRight}
            onPress={handleNext}
            accessibilityLabel={isLastChapter ? t('intro.start_journey') : t('intro.next')}
          />
        </View>

        {/* Narrative Content */}
        <Animated.View style={[styles.contentBox, { opacity: fadeAnim }]}>
          <View style={styles.chapterTag}>
            <Text variant="label" color={theme.color.accent.gold}>
              ✦ {t(currentChapter.subtitleKey)}
            </Text>
          </View>

          <Text variant="display" color={theme.color.text.primary}>
            {t(currentChapter.titleKey)}
          </Text>

          <Text
            variant="body"
            color={theme.color.text.secondary}
            style={styles.bodyText}
          >
            {t(currentChapter.bodyKey)}
          </Text>

          {/* Action Buttons */}
          <View style={styles.footerControls}>
            {currentChapterIndex > 0 && (
              <Button
                title={t('intro.previous')}
                variant="secondary"
                style={styles.prevButton}
                onPress={handlePrevious}
              />
            )}

            <Button
              title={isLastChapter ? t('intro.start_journey') : t('intro.next')}
              variant="primary"
              style={styles.nextButton}
              onPress={handleNext}
            />
          </View>
        </Animated.View>
      </SafeAreaView>
    </View>
  );
}
