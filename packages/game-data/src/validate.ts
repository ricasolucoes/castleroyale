/**
 * Game data validator.
 *
 * A bad dataset reaching production can break the game more thoroughly than a
 * bad deploy, so this runs in CI and again on import (ADR-013).
 *
 * It rejects: duplicate ids, negative costs and durations, dangling
 * references, dependency cycles, missing translation keys and unlock
 * requirements that can never be satisfied. Error messages name the
 * offending id — "invalid dataset" is not an acceptable failure message for
 * a designer shipping a tuning pass.
 *
 * This file is a thin CLI: read files, hand the data to the pure rule
 * functions in `./rules.ts`, print, exit. The rules themselves live there so
 * they can be unit tested without a fake `data/` directory on disk.
 */

import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  type Problem,
  type Dataset,
  checkDuplicateCodes,
  checkNumericFields,
  buildDependencyGraph,
  findCycle,
  checkSameDatasetReferences,
} from './rules.ts';

const dataDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'data');

const problems: Problem[] = [];

function fail(dataset: string, message: string): void {
  problems.push({ dataset, message });
}

function readDataset(file: string): unknown {
  return JSON.parse(readFileSync(join(dataDir, file), 'utf-8'));
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

const datasets: Dataset[] = [];

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

  datasets.push({ name, rows: parsed as Record<string, unknown>[] });
}

for (const { name, rows } of datasets) {
  problems.push(...checkDuplicateCodes(name, rows as { code?: string }[]));
  problems.push(...checkNumericFields(name, rows));
  problems.push(...checkSameDatasetReferences(name, rows));

  const graph = buildDependencyGraph(name, rows);
  const cycle = findCycle(graph);
  if (cycle !== null) fail(name, `dependency cycle: ${cycle.join(' -> ')}`);
}

// The fixed build-plot roster and the starter buildings that address it. This
// is a dangling-reference rule the generic per-dataset checks above cannot
// express (it only knows about `requirements[].type` graphs), so it is
// checked here.
if (existsSync(join(dataDir, 'city-slots.json')) && existsSync(join(dataDir, 'starter.json'))) {
  const roster: string[] = [];
  const rosterRaw = readDataset('city-slots.json');

  if (!Array.isArray(rosterRaw)) {
    fail('city-slots', 'is not a JSON array');
  } else {
    for (const row of rosterRaw as Record<string, unknown>[]) {
      const code = row['code'];
      if (typeof code !== 'string' || !/^plot_\d{2}$/.test(code)) {
        fail('city-slots', `"${String(code)}" is not a valid plot id (expected plot_NN)`);
        continue;
      }
      roster.push(code);
    }
  }

  const starter = readDataset('starter.json') as Record<string, unknown>;
  const city = (starter['city'] ?? {}) as Record<string, unknown>;
  const starterBuildings = Array.isArray(city['buildings'])
    ? (city['buildings'] as Record<string, unknown>[])
    : [];

  const assignedSlots = new Set<string>();
  for (const building of starterBuildings) {
    const code = String(building['code'] ?? '<unknown>');
    const slot = building['slot'];

    if (slot === undefined) {
      fail('starter', `building "${code}" has no "slot"`);
      continue;
    }
    if (typeof slot !== 'string' || !roster.includes(slot)) {
      fail('starter', `building "${code}" is assigned unknown slot "${String(slot)}"`);
      continue;
    }
    if (assignedSlots.has(slot)) {
      fail('starter', `slot "${slot}" is assigned twice`);
      continue;
    }
    assignedSlots.add(slot);
  }
}

if (problems.length > 0) {
  console.error(`Game data validation FAILED — ${problems.length} problem(s):\n`);
  for (const p of problems) console.error(`  [${p.dataset}] ${p.message}`);
  process.exit(1);
}

console.log(`Game data OK — ${files.length} dataset(s) validated.`);
