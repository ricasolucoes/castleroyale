/**
 * Translation catalogues.
 *
 * Every player-visible string comes from here — no literal strings in
 * components or controllers. Enforced by a lint rule and an architecture test
 * from Phase 42; write new strings through the catalogue from the start.
 */

export const SUPPORTED_LOCALES = ['pt-BR', 'en', 'es'] as const;

export type Locale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: Locale = 'en';

/** A flat map of dotted key to translated string. */
export type Catalogue = Record<string, string>;

export function isSupportedLocale(value: string): value is Locale {
  return (SUPPORTED_LOCALES as readonly string[]).includes(value);
}

import enNavigation from '../locales/en/navigation.json';
import esNavigation from '../locales/es/navigation.json';
import ptBRNavigation from '../locales/pt-BR/navigation.json';

/**
 * Resolve a requested locale to one we actually ship, falling back to the
 * language subtag before giving up (`pt-PT` -> `pt-BR` is deliberate: closer
 * than English for a Portuguese speaker).
 */
export function resolveLocale(requested: string | undefined): Locale {
  if (requested === undefined) return DEFAULT_LOCALE;
  if (isSupportedLocale(requested)) return requested;

  const language = requested.split('-')[0];
  const match = SUPPORTED_LOCALES.find((l) => l.split('-')[0] === language);

  return match ?? DEFAULT_LOCALE;
}

export const CATALOGUES = {
  en: { navigation: enNavigation },
  es: { navigation: esNavigation },
  'pt-BR': { navigation: ptBRNavigation },
};
