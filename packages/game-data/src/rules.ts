/**
 * Game data validation rules.
 *
 * Every rule here is a pure function: it takes data in and returns the
 * `Problem[]` it found, never touching the filesystem and never printing.
 * `validate.ts` is the only thing that reads files and prints — these
 * functions exist so each rule can be exercised directly by a test, without
 * standing up a fake `data/` directory on disk.
 *
 * Error messages name the offending id — "invalid dataset" is not an
 * acceptable failure message for a designer shipping a tuning pass.
 */

export type Problem = { dataset: string; message: string };

export type Dataset = { name: string; rows: Record<string, unknown>[] };

/**
 * Dataset file name to the `requirement.type` it maps to.
 *
 * Explicit rather than derived: naive singularisation turns "technologies"
 * into "technologie", which silently matches nothing and disables cycle and
 * dangling-reference detection entirely.
 */
export const REQUIREMENT_TYPE_BY_DATASET: Record<string, string> = {
  buildings: 'building',
  technologies: 'technology',
  units: 'unit',
};

export function checkDuplicateCodes(dataset: string, rows: { code?: string }[]): Problem[] {
  const problems: Problem[] = [];
  const seen = new Set<string>();

  for (const row of rows) {
    if (row.code === undefined) {
      problems.push({ dataset, message: 'an entry is missing its "code"' });
      continue;
    }
    if (seen.has(row.code)) problems.push({ dataset, message: `duplicate code "${row.code}"` });
    seen.add(row.code);
  }

  return problems;
}

function checkNonNegative(dataset: string, code: string, label: string, value: unknown): Problem[] {
  const problems: Problem[] = [];

  if (typeof value !== 'number' || Number.isNaN(value)) {
    problems.push({ dataset, message: `"${code}" has a non-numeric ${label}` });
    return problems;
  }
  if (!Number.isInteger(value)) {
    problems.push({
      dataset,
      message: `"${code}" has a fractional ${label} (${value}) — economy values are integers`,
    });
  }
  if (value < 0) {
    problems.push({ dataset, message: `"${code}" has a negative ${label} (${value})` });
  }

  return problems;
}

export function checkNumericFields(dataset: string, rows: Record<string, unknown>[]): Problem[] {
  const problems: Problem[] = [];

  for (const row of rows) {
    const code = String(row['code'] ?? '<unknown>');
    const levels = Array.isArray(row['levels']) ? (row['levels'] as Record<string, unknown>[]) : [];

    for (const level of levels) {
      for (const [key, value] of Object.entries((level['cost'] ?? {}) as Record<string, unknown>)) {
        problems.push(...checkNonNegative(dataset, code, `cost.${key}`, value));
      }
      for (const key of ['build_time_seconds', 'research_time_seconds', 'training_time_seconds']) {
        if (key in level) problems.push(...checkNonNegative(dataset, code, key, level[key]));
      }
    }
  }

  return problems;
}

/** Same-dataset dependency graph: edges are `requirements[].type === selfType`. */
export function buildDependencyGraph(
  dataset: string,
  rows: Record<string, unknown>[],
): Map<string, string[]> {
  const graph = new Map<string, string[]>();
  const selfType = REQUIREMENT_TYPE_BY_DATASET[dataset];

  for (const row of rows) {
    const code = String(row['code'] ?? '<unknown>');
    const levels = Array.isArray(row['levels']) ? (row['levels'] as Record<string, unknown>[]) : [];

    for (const level of levels) {
      const requirements = Array.isArray(level['requirements'])
        ? (level['requirements'] as Record<string, unknown>[])
        : [];
      const deps =
        selfType === undefined
          ? []
          : requirements.filter((r) => r['type'] === selfType).map((r) => String(r['code']));
      graph.set(code, [...(graph.get(code) ?? []), ...deps]);
    }
  }

  return graph;
}

/** Depth-first cycle detection over an explicit dependency graph. */
export function findCycle(graph: Map<string, string[]>): string[] | null {
  const state = new Map<string, 'visiting' | 'done'>();
  const stack: string[] = [];

  function visit(node: string): string[] | null {
    const current = state.get(node);
    if (current === 'done') return null;
    if (current === 'visiting') return [...stack.slice(stack.indexOf(node)), node];

    state.set(node, 'visiting');
    stack.push(node);
    for (const next of graph.get(node) ?? []) {
      const cycle = visit(next);
      if (cycle !== null) return cycle;
    }
    stack.pop();
    state.set(node, 'done');
    return null;
  }

  for (const node of graph.keys()) {
    const cycle = visit(node);
    if (cycle !== null) return cycle;
  }
  return null;
}

/** Dangling references within the same dataset (e.g. a technology requiring a technology). */
export function checkSameDatasetReferences(dataset: string, rows: Record<string, unknown>[]): Problem[] {
  const problems: Problem[] = [];
  const graph = buildDependencyGraph(dataset, rows);
  const known = new Set(rows.map((r) => String(r['code'])));

  for (const [code, deps] of graph) {
    for (const dep of deps) {
      if (!known.has(dep)) problems.push({ dataset, message: `"${code}" requires unknown "${dep}"` });
    }
  }

  return problems;
}

/** Walks a dotted path (`"technologies.agriculture"`) into a nested object. */
function resolveDottedKey(catalogue: unknown, key: string): unknown {
  let current: unknown = catalogue;
  for (const part of key.split('.')) {
    if (typeof current !== 'object' || current === null) return undefined;
    current = (current as Record<string, unknown>)[part];
  }
  return current;
}

/**
 * Any row property ending in `_key` (this catches `name_key` and
 * `description_key` without hardcoding either, so a future `tooltip_key` is
 * covered automatically) must resolve against every locale catalogue.
 */
export function checkTranslationKeys(
  dataset: string,
  rows: Record<string, unknown>[],
  catalogues: Record<string, unknown>,
): Problem[] {
  const problems: Problem[] = [];

  for (const row of rows) {
    const code = String(row['code'] ?? '<unknown>');

    for (const [property, value] of Object.entries(row)) {
      if (!property.endsWith('_key') || typeof value !== 'string') continue;

      for (const [locale, catalogue] of Object.entries(catalogues)) {
        if (resolveDottedKey(catalogue, value) === undefined) {
          problems.push({
            dataset,
            message: `"${code}" has ${property} "${value}" which is missing from the ${locale} catalogue`,
          });
        }
      }
    }
  }

  return problems;
}

/**
 * `"type:code"` -> `max_level`, built once from every dataset that carries a
 * `requirement.type` (buildings, technologies, units). Requirements target
 * this map regardless of which dataset they were declared in.
 */
export function buildMaxLevelsByTypeAndCode(datasets: Dataset[]): Map<string, number> {
  const maxLevels = new Map<string, number>();

  for (const { name, rows } of datasets) {
    const type = REQUIREMENT_TYPE_BY_DATASET[name];
    if (type === undefined) continue;

    for (const row of rows) {
      const code = row['code'];
      const maxLevel = row['max_level'];
      if (typeof code === 'string' && typeof maxLevel === 'number') {
        maxLevels.set(`${type}:${code}`, maxLevel);
      }
    }
  }

  return maxLevels;
}

/**
 * A requirement naming a level higher than its target's `max_level` can
 * never be satisfied. A self-requirement at a level at or above the level
 * that declares it is the same failure in disguise — level N implies level
 * N-1 was already researched, so a self-requirement at a *lower* level is
 * redundant but harmless, not an error.
 */
export function checkRequirementSatisfiable(
  dataset: string,
  rows: Record<string, unknown>[],
  maxLevelsByTypeAndCode: Map<string, number>,
): Problem[] {
  const problems: Problem[] = [];
  const selfType = REQUIREMENT_TYPE_BY_DATASET[dataset];

  for (const row of rows) {
    const code = String(row['code'] ?? '<unknown>');
    const levels = Array.isArray(row['levels']) ? (row['levels'] as Record<string, unknown>[]) : [];

    for (const level of levels) {
      const n = Number(level['level']);
      const requirements = Array.isArray(level['requirements'])
        ? (level['requirements'] as Record<string, unknown>[])
        : [];

      for (const requirement of requirements) {
        const type = String(requirement['type']);
        const reqCode = String(requirement['code']);
        const reqLevel = Number(requirement['level']);
        const maxLevel = maxLevelsByTypeAndCode.get(`${type}:${reqCode}`);

        if (maxLevel !== undefined && reqLevel > maxLevel) {
          problems.push({
            dataset,
            message: `"${code}" level ${n} requires ${type} "${reqCode}" level ${reqLevel}, but its max level is ${maxLevel}`,
          });
        }

        if (type === selfType && reqCode === code && reqLevel >= n) {
          problems.push({
            dataset,
            message: `"${code}" level ${n} requires itself at level ${reqLevel}`,
          });
        }
      }
    }
  }

  return problems;
}

/**
 * Cross-dataset dangling references: `checkSameDatasetReferences` only
 * validates `requirements[].type === selfType` edges (e.g. a technology
 * requiring a technology). This validates the rest — a technology requiring
 * a nonexistent building, and vice versa.
 */
export function checkCrossDatasetReferences(datasets: Dataset[]): Problem[] {
  const problems: Problem[] = [];

  const knownCodesByDataset = new Map<string, Set<string>>();
  for (const { name, rows } of datasets) {
    knownCodesByDataset.set(name, new Set(rows.map((r) => String(r['code']))));
  }

  const datasetByType = new Map<string, string>();
  for (const [dataset, type] of Object.entries(REQUIREMENT_TYPE_BY_DATASET)) {
    datasetByType.set(type, dataset);
  }

  for (const { name, rows } of datasets) {
    const selfType = REQUIREMENT_TYPE_BY_DATASET[name];

    for (const row of rows) {
      const code = String(row['code'] ?? '<unknown>');
      const levels = Array.isArray(row['levels']) ? (row['levels'] as Record<string, unknown>[]) : [];

      for (const level of levels) {
        const requirements = Array.isArray(level['requirements'])
          ? (level['requirements'] as Record<string, unknown>[])
          : [];

        for (const requirement of requirements) {
          const type = String(requirement['type']);
          if (type === selfType) continue; // same-dataset edges are checkSameDatasetReferences's job

          const targetDataset = datasetByType.get(type);
          if (targetDataset === undefined) continue; // e.g. "nobility"/"player_level" — no dataset backs these (yet)

          const reqCode = String(requirement['code']);
          const known = knownCodesByDataset.get(targetDataset);
          if (known === undefined || !known.has(reqCode)) {
            problems.push({
              dataset: name,
              message: `"${code}" requires ${type} "${reqCode}", which is not in ${targetDataset}.json`,
            });
          }
        }
      }
    }
  }

  return problems;
}
