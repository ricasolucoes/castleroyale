import { MIN_TOUCH_TARGET, palette, spacing } from '@castleroyale/tooling/design-tokens';

/**
 * Guards on the design system that are cheap to assert and expensive to
 * discover late. The full accessibility sweep lands in GSD Phase 41.
 */

describe('design tokens', () => {
  it('defines the same token shape in both themes', () => {
    expect(Object.keys(palette.light)).toEqual(Object.keys(palette.dark));
    expect(Object.keys(palette.light.text)).toEqual(Object.keys(palette.dark.text));
  });

  it('uses a 4pt spacing scale', () => {
    for (const value of Object.values(spacing)) {
      expect(value % 4).toBe(0);
    }
  });

  it('keeps the minimum touch target at 44pt', () => {
    // Below this, real thumbs miss. Changing it needs an accessibility review.
    expect(MIN_TOUCH_TARGET).toBe(44);
  });

  it('expresses every colour as a hex value', () => {
    const walk = (node: unknown): void => {
      if (typeof node === 'string') {
        expect(node).toMatch(/^#[0-9A-Fa-f]{6}$/);
        return;
      }
      if (typeof node === 'object' && node !== null) {
        Object.values(node).forEach(walk);
      }
    };
    walk(palette);
  });
});
