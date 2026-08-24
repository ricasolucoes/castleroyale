/**
 * Catalogue completeness check.
 *
 * A missing key must fail CI, never render as `city.building.upgrade.title` in
 * front of a player (Phase 42).
 */

import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SUPPORTED_LOCALES, DEFAULT_LOCALE, type Locale } from './index.ts';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', 'locales');

function load(locale: Locale): Record<string, string> {
  const dir = join(root, locale);
  if (!existsSync(dir)) return {};

  const merged: Record<string, string> = {};
  for (const file of readdirSync(dir).filter((f) => f.endsWith('.json'))) {
    const namespace = file.replace(/\.json$/, '');
    const parsed: unknown = JSON.parse(readFileSync(join(dir, file), 'utf-8'));
    for (const [key, value] of Object.entries(parsed as Record<string, string>)) {
      merged[`${namespace}.${key}`] = value;
    }
  }
  return merged;
}

const reference = load(DEFAULT_LOCALE);
const referenceKeys = Object.keys(reference);
const problems: string[] = [];

if (referenceKeys.length === 0) {
  console.log(`No strings authored yet (reference locale: ${DEFAULT_LOCALE}). Nothing to check.`);
  process.exit(0);
}

for (const locale of SUPPORTED_LOCALES) {
  if (locale === DEFAULT_LOCALE) continue;

  const catalogue = load(locale);
  const missing = referenceKeys.filter((k) => !(k in catalogue));
  const extra = Object.keys(catalogue).filter((k) => !(k in reference));

  for (const key of missing) problems.push(`${locale}: missing "${key}"`);
  for (const key of extra) problems.push(`${locale}: unknown key "${key}" (not in ${DEFAULT_LOCALE})`);
}

if (problems.length > 0) {
  console.error(`Localisation check failed — ${problems.length} problem(s):\n`);
  for (const p of problems) console.error(`  ${p}`);
  process.exit(1);
}

console.log(`Localisation OK — ${referenceKeys.length} keys across ${SUPPORTED_LOCALES.length} locales.`);
