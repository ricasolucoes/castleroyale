import React, { useState, useEffect } from 'react';
import { View, type ViewProps } from 'react-native';
import { Text } from './Text';

export interface TimerProps extends ViewProps {
  /** The future timestamp to countdown to, in milliseconds */
  targetTimestamp: number;
  /** Optional callback when the countdown reaches 0 */
  onFinish?: () => void;
}

export function formatDuration(ms: number): string {
  if (ms <= 0) return '00:00:00';
  const totalSeconds = Math.floor(ms / 1000);
  const h = Math.floor(totalSeconds / 3600);
  const m = Math.floor((totalSeconds % 3600) / 60);
  const s = totalSeconds % 60;

  const pad = (num: number) => num.toString().padStart(2, '0');
  return `${pad(h)}:${pad(m)}:${pad(s)}`;
}

/**
 * Timer component that counts down to a given timestamp.
 * In future phases, this will integrate with a server-synced Clock utility.
 */
export function Timer({ targetTimestamp, onFinish, style, ...rest }: TimerProps) {
  const [timeLeft, setTimeLeft] = useState(() => targetTimestamp - Date.now());

  useEffect(() => {
    // Check immediately on mount or target change
    let currentLeft = targetTimestamp - Date.now();
    setTimeLeft(currentLeft);

    if (currentLeft <= 0) {
      if (currentLeft < 0) {
        onFinish?.();
      }
      return;
    }

    const interval = setInterval(() => {
      currentLeft = targetTimestamp - Date.now();
      if (currentLeft <= 0) {
        setTimeLeft(0);
        clearInterval(interval);
        onFinish?.();
      } else {
        setTimeLeft(currentLeft);
      }
    }, 1000);

    return () => clearInterval(interval);
  }, [targetTimestamp, onFinish]);

  return (
    <View style={style} {...rest}>
      <Text variant="numeric">{formatDuration(timeLeft)}</Text>
    </View>
  );
}
