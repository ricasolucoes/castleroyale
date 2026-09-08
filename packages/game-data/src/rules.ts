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
