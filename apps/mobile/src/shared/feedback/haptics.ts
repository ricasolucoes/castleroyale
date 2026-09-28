import * as Haptics from 'expo-haptics';

/**
 * Physical feedback for events the player caused.
 *
 * Three rules keep this from becoming noise:
 *
 * 1. Only player-initiated, state-changing events fire. A tap that selects, an
 *    order the server accepted, an order it refused. Never a render, never a
 *    poll, never a countdown tick — nothing on a timer may call in here.
 * 2. Intensity tracks consequence: selecting is the lightest thing the OS
 *    offers, committing an order is a notification.
 * 3. It can always fail silently. Haptics are unavailable on web, on devices
 *    without a taptic engine, and while the OS is in low-power mode; a missing
 *    buzz must never surface as an error, because nothing here carries
 *    information the screen is not already showing.
 */

function fireAndForget(run: () => Promise<unknown>): void {
  try {
    void run().catch(() => undefined);
  } catch {
    // Module unavailable on this platform. Silence is the correct outcome.
  }
}

/** A plot, a tile, a pip — the player pointed at something. */
export function selectionFeedback(): void {
  fireAndForget(() => Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light));
}

/** The server accepted an order: construction started, research queued. */
export function commitFeedback(): void {
  fireAndForget(() => Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success));
}

/** The server refused: not enough resources, queue full, already at max level. */
export function rejectFeedback(): void {
  fireAndForget(() => Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error));
}
