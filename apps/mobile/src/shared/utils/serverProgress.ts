import { useEffect, useState } from 'react';

/**
 * The 1 Hz, device-clock-skew-corrected "percent complete" arithmetic first
 * written for `ConstructionQueueStrip`'s pip and reused verbatim here, rather
 * than hand-copied into a second implementation, so the two can never silently
 * drift apart. `ConstructionQueueStrip.tsx` itself is left on its own inline
 * copy — it already ships tested — but any new timer-progress surface should
 * import this instead of writing a third one.
 */
export function clampPercent(value: number): number {
  if (Number.isNaN(value)) return 0;
  return Math.min(100, Math.max(0, value));
}

/**
 * Percent complete between two server timestamps, corrected for the gap
 * between the device clock and the server clock (`clockSkewMs =
 * nowMs - Date.parse(serverTime)`), matching `ConstructionQueueStrip`'s own
 * calculation and its `finishesMs === startedMs` guard against a division by
 * zero.
 */
export function progressPercent(
  startedAt: string,
  finishesAt: string,
  nowMs: number,
  clockSkewMs: number,
): number {
  const startedMs = Date.parse(startedAt) + clockSkewMs;
  const finishesMs = Date.parse(finishesAt) + clockSkewMs;

  if (finishesMs === startedMs) return 100;
  return clampPercent(((nowMs - startedMs) / (finishesMs - startedMs)) * 100);
}

/**
 * The device clock, re-read at a 1 Hz cadence and cleared on unmount — the same
 * "plain update, no animated roll" policy `Timer.tsx` and
 * `ConstructionQueueStrip.tsx` already use. `enabled` lets a caller skip the
 * interval entirely when nothing on screen needs it (an available or completed
 * node never ticks).
 */
export function useTickingNow(enabled: boolean, intervalMs = 1000): number {
  const [nowMs, setNowMs] = useState(() => Date.now());

  useEffect(() => {
    if (!enabled) return;

    const interval = setInterval(() => setNowMs(Date.now()), intervalMs);
    return () => clearInterval(interval);
  }, [enabled, intervalMs]);

  return nowMs;
}
