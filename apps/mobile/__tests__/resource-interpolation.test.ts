import { interpolateResources } from '../src/features/economy/interpolation/interpolateResources';

function snapshot(overrides: Partial<Parameters<typeof interpolateResources>[0]> = {}) {
  return {
    current: { food: 100, wood: 100, stone: 100, iron: 100, gold: 100 },
    capacity: { food: 1000, wood: 1000, stone: 1000, iron: 1000, gold: 1000 },
    rate: { food: 3600, wood: 3600, stone: 3600, iron: 3600, gold: 3600 },
    capturedAt: 0,
    ...overrides,
  };
}

describe('interpolateResources', () => {
  it('advances one unit after one second at a rate of 3600 units/hour', () => {
    const result = interpolateResources(
      { current: { food: 100 }, capacity: { food: 1000 }, rate: { food: 3600 }, capturedAt: 0 },
      1000,
    );

    expect(result.food).toBe(101);
  });

  it('clamps at capacity and stops climbing, with no separate flag', () => {
    const result = interpolateResources(
      { current: { food: 990 }, capacity: { food: 1000 }, rate: { food: 3600 }, capturedAt: 0 },
      60_000,
    );

    expect(result.food).toBe(1000);
  });

  it('never extrapolates backward: a stale "now" returns the captured values unchanged', () => {
    const result = interpolateResources(snapshot({ capturedAt: 60_000 }), 0);

    expect(result).toEqual({ food: 100, wood: 100, stone: 100, iron: 100, gold: 100 });
  });

  it('floors a fractional projection to an integer', () => {
    const result = interpolateResources(
      { current: { food: 100 }, capacity: { food: 0 }, rate: { food: 3600 }, capturedAt: 0 },
      900, // 900ms at 3600/hr = 0.9 units
    );

    expect(result.food).toBe(100);
  });

  it('treats zero capacity as unclamped growth, not a permanent cap', () => {
    const result = interpolateResources(
      { current: { food: 100 }, capacity: { food: 0 }, rate: { food: 3600 }, capturedAt: 0 },
      3_600_000, // one full hour
    );

    expect(result.food).toBe(3700);
  });

  it('drains a negative rate toward zero and floors there, never below', () => {
    const result = interpolateResources(
      { current: { food: 10 }, capacity: { food: 1000 }, rate: { food: -3600 }, capturedAt: 0 },
      3_600_000, // one full hour of upkeep against only 10 units
    );

    expect(result.food).toBe(0);
  });
});
