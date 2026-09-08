import { getLocales } from 'expo-localization';
import { CATALOGUES, resolveLocale } from '@castleroyale/localization';

export type TranslateFn = (key: string, values?: Record<string, string | number>) => string;

export function useTranslation() {
  const locales = getLocales();
  const languageTag = locales && locales.length > 0 ? locales[0].languageTag : undefined;
  const locale = resolveLocale(languageTag);

  const t = (key: string, values: Record<string, string | number> = {}) => {
    const value = key.split('.').reduce((o: unknown, i: string) => (o as Record<string, unknown>)?.[i], CATALOGUES[locale]);
    if (typeof value !== 'string') return key;

    return value.replace(/\{(\w+)\}/g, (match, name: string) => (
      name in values ? String(values[name]) : match
    ));
  };

  return { t, locale };
}
