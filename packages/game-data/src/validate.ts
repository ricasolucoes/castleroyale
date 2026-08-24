/**
 * Game data validator.
 *
 * A bad dataset reaching production can break the game more thoroughly than a
 * bad deploy, so this runs in CI and again on import (ADR-013).
 *
 * It rejects: duplicate ids, negative costs and durations, dangling references
 * and dependency cycles. Error messages name the offending id — "invalid
 * dataset" is not an acceptable failure message for a designer shipping a
 * tuning pass.
 *
 * The full rule set (including translation-key and unlock-reachability checks)
 * is completed in GSD Phase 10.
 */

import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const dataDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'data');

/**
 * Dataset file name to the `requirement.type` it maps to.
 *
 * Explicit rather than derived: naive singularisation turns "technologies"
 * into "technologie", which silently matches nothing and disables cycle and
 * dangling-reference detection entirely.
 */
const REQUIREMENT_TYPE_BY_DATASET: Record<string, string> = {
  buildings: 'building',
  technologies: 'technology',
  units: 'unit',
};

type Problem = { dataset: string; message: string };
const problems: Problem[] = [];

function fail(dataset: string, message: string): void {
  problems.push({ dataset, message });
}

function readDataset(file: string): unknown {
  return JSON.parse(readFileSync(join(dataDir, file), 'utf-8'));
}

function checkDuplicateCodes(dataset: string, rows: { code?: string }[]): void {
  const seen = new Set<string>();
  for (const row of rows) {
    if (row.code === undefined) {
      fail(dataset, 'an entry is missing its "code"');
      continue;
    }
    if (seen.has(row.code)) fail(dataset, `duplicate code "${row.code}"`);
    seen.add(row.code);
  }
}

function checkNonNegative(dataset: string, code: string, label: string, value: unknown): void {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    fail(dataset, `"${code}" has a non-numeric ${label}`);
    return;
  }
  if (!Number.isInteger(value)) {
    fail(dataset, `"${code}" has a fractional ${label} (${value}) — economy values are integers`);
  }
  if (value < 0) {
    fail(dataset, `"${code}" has a negative ${label} (${value})`);
  }
}

/** Depth-first cycle detection over an explicit dependency graph. */
function findCycle(graph: Map<string, string[]>): string[] | null {
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

if (!existsSync(dataDir)) {
  console.log('No data/ directory yet — nothing to validate.');
  process.exit(0);
}

const files = readdirSync(dataDir).filter((f) => f.endsWith('.json'));

if (files.length === 0) {
  console.log('No datasets authored yet. The first arrives with GSD Phase 09 (buildings).');
  process.exit(0);
}

for (const file of files) {
  const name = file.replace(/\.json$/, '');
  let parsed: unknown;

  try {
    parsed = readDataset(file);
  } catch (error) {
    fail(name, `is not valid JSON: ${(error as Error).message}`);
    continue;
  }

  if (!Array.isArray(parsed)) continue;

  const rows = parsed as Record<string, unknown>[];
  checkDuplicateCodes(name, rows as { code?: string }[]);

  const graph = new Map<string, string[]>();

  for (const row of rows) {
    const code = String(row['code'] ?? '<unknown>');
    const levels = Array.isArray(row['levels']) ? (row['levels'] as Record<string, unknown>[]) : [];

    for (const level of levels) {
      for (const [key, value] of Object.entries((level['cost'] ?? {}) as Record<string, unknown>)) {
        checkNonNegative(name, code, `cost.${key}`, value);
      }
      for (const key of ['build_time_seconds', 'research_time_seconds', 'training_time_seconds']) {
        if (key in level) checkNonNegative(name, code, key, level[key]);
      }

      const requirements = Array.isArray(level['requirements'])
        ? (level['requirements'] as Record<string, unknown>[])
        : [];
      const selfType = REQUIREMENT_TYPE_BY_DATASET[name];
      const deps =
        selfType === undefined
          ? []
          : requirements.filter((r) => r['type'] === selfType).map((r) => String(r['code']));
      graph.set(code, [...(graph.get(code) ?? []), ...deps]);
    }
  }

  // Dangling references within the same dataset.
  const known = new Set(rows.map((r) => String(r['code'])));
  for (const [code, deps] of graph) {
    for (const dep of deps) {
      if (!known.has(dep)) fail(name, `"${code}" requires unknown "${dep}"`);
    }
  }

  const cycle = findCycle(graph);
  if (cycle !== null) {
    fail(name, `dependency cycle: ${cycle.join(' -> ')}`);
  }
}

if (problems.length > 0) {
  console.error(`Game data validation FAILED — ${problems.length} problem(s):\n`);
  for (const p of problems) console.error(`  [${p.dataset}] ${p.message}`);
  process.exit(1);
}

console.log(`Game data OK — ${files.length} dataset(s) validated.`);
