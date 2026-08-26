import { getLocales } from 'expo-localization';
import { CATALOGUES, resolveLocale } from '@dominion/localization';

export function useTranslation() {
  const locales = getLocales();
  const languageTag = locales && locales.length > 0 ? locales[0].languageTag : undefined;
  const locale = resolveLocale(languageTag);

  const t = (key: string) => {
    const value = key.split('.').reduce((o: unknown, i: string) => (o as Record<string, unknown>)?.[i], CATALOGUES[locale]);
    return typeof value === 'string' ? value : key;
  };

  return { t, locale };
}
