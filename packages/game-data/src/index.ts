/**
 * Versioned, data-driven game balance.
 *
 * Every balance number lives here as JSON, is validated in CI, and is imported
 * into the database by `php artisan game:import-data`. The database is the
 * runtime source of truth; this package is the reviewable one (ADR-013).
 *
 * **No balance number may appear in application code.**
 */

/** Percentage modifiers are integer permille: 1500 means 150% (ADR-010). */
export type Permille = number;

/** Whole units only. Absent keys are zero. */
export type ResourceCost = {
  food?: number;
  wood?: number;
  stone?: number;
  iron?: number;
  gold?: number;
};

/**
 * A typed effect descriptor. Technology and hero bonuses share one resolver —
 * there is not a second effect system.
 */
export type Effect = {
  target: string;
  operation: 'add' | 'multiply';
  value: number;
};

export type Requirement = {
  type: 'building' | 'technology' | 'nobility' | 'player_level';
  code: string;
  level: number;
};

export type BuildingLevel = {
  level: number;
  cost: ResourceCost;
  build_time_seconds: number;
  requirements: Requirement[];
  effects: Effect[];
  capacity?: number;
};

export type Building = {
  code: string;
  name_key: string;
  category: string;
  max_level: number;
  levels: BuildingLevel[];
};

export type Unit = {
  code: string;
  name_key: string;
  class: 'infantry' | 'ranged' | 'cavalry' | 'recon' | 'siege';
  attack: number;
  defence: number;
  health: number;
  speed: number;
  range: number;
  capacity: number;
  training_cost: ResourceCost;
  training_time_seconds: number;
  upkeep: ResourceCost;
  siege_damage: number;
};

/** `(attacker_class, defender_class) -> permille modifier`. Data, never a branch. */
export type CounterMatrix = Record<string, Record<string, Permille>>;

export type Technology = {
  code: string;
  name_key: string;
  category: string;
  max_level: number;
  levels: {
    level: number;
    cost: ResourceCost;
    research_time_seconds: number;
    requirements: Requirement[];
    effects: Effect[];
  }[];
};

/** A fixed, stable build plot. Identity never changes once shipped — the client addresses it. */
export type CitySlot = {
  code: string;
};

export type Dataset = {
  buildings?: Building[];
  units?: Unit[];
  counters?: CounterMatrix;
  technologies?: Technology[];
  'city-slots'?: CitySlot[];
};

export const DATASET_NAMES = ['buildings', 'units', 'counters', 'technologies', 'city-slots'] as const;
export type DatasetName = (typeof DATASET_NAMES)[number];
