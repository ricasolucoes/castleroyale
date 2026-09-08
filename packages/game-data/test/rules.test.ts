/**
 * One red-path test per validator rule. The point of every test here is the
 * message, not just the boolean — docs/game-design/technology.md mandates
 * that error messages name the offending id, so every assertion checks that
 * the id actually shows up in the message, not merely that validation failed.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  type Dataset,
  checkDuplicateCodes,
  checkNumericFields,
  buildDependencyGraph,
  findCycle,
  checkSameDatasetReferences,
  checkTranslationKeys,
  checkRequirementSatisfiable,
  checkCrossDatasetReferences,
  buildMaxLevelsByTypeAndCode,
} from '../src/rules.ts';

const here = dirname(fileURLToPath(import.meta.url));
const dataDir = join(here, '..', 'data');
const localesDir = join(here, '..', '..', 'localization', 'locales');

test('rejects a duplicate code and names it', () => {
  const problems = checkDuplicateCodes('technologies', [{ code: 'agriculture' }, { code: 'agriculture' }]);
  assert.ok(problems.some((p) => p.message.includes('agriculture')));
});

test('rejects a negative cost and names the technology and the resource', () => {
  const rows = [{ code: 'masonry', levels: [{ level: 1, cost: { wood: -5 } }] }];
  const problems = checkNumericFields('technologies', rows);
  assert.ok(problems.some((p) => p.message.includes('masonry')));
  assert.ok(problems.some((p) => p.message.includes('cost.wood')));
});

test('rejects a fractional cost', () => {
  const rows = [{ code: 'masonry', levels: [{ level: 1, cost: { wood: 1.5 } }] }];
  const problems = checkNumericFields('technologies', rows);
  assert.ok(problems.some((p) => p.message.includes('masonry')));
});

test('rejects a dangling same-dataset reference and names both codes', () => {
  const rows = [
    {
      code: 'logistics_core',
      levels: [{ level: 1, requirements: [{ type: 'technology', code: 'does_not_exist' }] }],
    },
    { code: 'masonry', levels: [{ level: 1, requirements: [] }] },
  ];
  const problems = checkSameDatasetReferences('technologies', rows);
  assert.ok(
    problems.some((p) => p.message.includes('logistics_core') && p.message.includes('does_not_exist')),
  );
});

test('detects a five-node cycle (a -> b -> c -> d -> e -> a)', () => {
  const graph = new Map<string, string[]>([
    ['a', ['b']],
    ['b', ['c']],
    ['c', ['d']],
    ['d', ['e']],
    ['e', ['a']],
  ]);
  const cycle = findCycle(graph);
  assert.notEqual(cycle, null);
  for (const node of ['a', 'b', 'c', 'd', 'e']) {
    assert.ok(cycle?.includes(node), `cycle should include "${node}"`);
  }
});

test('a diamond dependency (shared descendant) is not a cycle', () => {
  // a depends on both b and c, and both b and c depend on d. d is a shared
  // descendant, not a cycle — an implementation that confused "visited" with
  // "on the current stack" would wrongly flag this.
  const graph = new Map<string, string[]>([
    ['a', ['b', 'c']],
    ['b', ['d']],
    ['c', ['d']],
    ['d', []],
  ]);
  assert.equal(findCycle(graph), null);
});

test('rejects a missing translation key and names the key and the locale', () => {
  const rows = [{ code: 'mytech', name_key: 'technologies.mytech' }];
  const catalogues: Record<string, unknown> = {
    en: { technologies: { mytech: 'My Tech' } },
    es: { technologies: {} },
    'pt-BR': { technologies: { mytech: 'Minha Tecnologia' } },
  };
  const problems = checkTranslationKeys('technologies', rows, catalogues);
  assert.ok(problems.some((p) => p.message.includes('technologies.mytech') && p.message.includes('es')));
});

test('rejects a requirement above the target max level', () => {
  const rows = [
    {
      code: 'overreach',
      levels: [{ level: 1, requirements: [{ type: 'technology', code: 'masonry', level: 9 }] }],
    },
  ];
  const maxLevels = new Map([['technology:masonry', 3]]);
  const problems = checkRequirementSatisfiable('technologies', rows, maxLevels);
  assert.ok(problems.some((p) => p.message.includes('masonry') && p.message.includes('9')));
});

test('rejects a self-requirement at an unreachable level', () => {
  const rows = [
    {
      code: 'agriculture',
      levels: [
        { level: 1, requirements: [] },
        { level: 2, requirements: [{ type: 'technology', code: 'agriculture', level: 2 }] },
      ],
    },
  ];
  const maxLevels = new Map([['technology:agriculture', 3]]);
  const problems = checkRequirementSatisfiable('technologies', rows, maxLevels);
  assert.ok(problems.some((p) => p.message.includes('agriculture')));
});

test('allows a self-requirement at a lower level', () => {
  // Level N implies level N-1 was already researched — redundant, not an error.
  const rows = [
    {
      code: 'agriculture',
      levels: [
        { level: 1, requirements: [] },
        { level: 2, requirements: [] },
        { level: 3, requirements: [{ type: 'technology', code: 'agriculture', level: 2 }] },
      ],
    },
  ];
  const maxLevels = new Map([['technology:agriculture', 3]]);
  const problems = checkRequirementSatisfiable('technologies', rows, maxLevels);
  assert.deepEqual(problems, []);
});

test('rejects a cross-dataset dangling reference', () => {
  const datasets: Dataset[] = [
    {
      name: 'technologies',
      rows: [
        { code: 'tech1', levels: [{ level: 1, requirements: [{ type: 'building', code: 'does_not_exist' }] }] },
      ],
    },
    { name: 'buildings', rows: [{ code: 'palace', levels: [] }] },
  ];
  const problems = checkCrossDatasetReferences(datasets);
  assert.ok(problems.some((p) => p.message.includes('tech1') && p.message.includes('buildings')));
});

test('accepts the real authored datasets', () => {
  const buildings = JSON.parse(readFileSync(join(dataDir, 'buildings.json'), 'utf-8')) as Record<
    string,
    unknown
  >[];
  const technologies = JSON.parse(readFileSync(join(dataDir, 'technologies.json'), 'utf-8')) as Record<
    string,
    unknown
  >[];
  const units = JSON.parse(readFileSync(join(dataDir, 'units.json'), 'utf-8')) as Record<string, unknown>[];

  const datasets: Dataset[] = [
    { name: 'buildings', rows: buildings },
    { name: 'technologies', rows: technologies },
    { name: 'units', rows: units },
  ];

  const catalogues: Record<string, unknown> = {};
  for (const locale of ['en', 'pt-BR', 'es']) {
    catalogues[locale] = JSON.parse(readFileSync(join(localesDir, locale, 'mvp.json'), 'utf-8'));
  }

  const problems = [];
  for (const { name, rows } of datasets) {
    problems.push(...checkDuplicateCodes(name, rows as { code?: string }[]));
    problems.push(...checkNumericFields(name, rows));
    problems.push(...checkSameDatasetReferences(name, rows));
    problems.push(...checkTranslationKeys(name, rows, catalogues));

    const cycle = findCycle(buildDependencyGraph(name, rows));
    if (cycle !== null) problems.push({ dataset: name, message: `dependency cycle: ${cycle.join(' -> ')}` });
  }
  problems.push(...checkCrossDatasetReferences(datasets));

  const maxLevels = buildMaxLevelsByTypeAndCode(datasets);
  for (const { name, rows } of datasets) {
    problems.push(...checkRequirementSatisfiable(name, rows, maxLevels));
  }

  assert.deepEqual(problems, []);
});
