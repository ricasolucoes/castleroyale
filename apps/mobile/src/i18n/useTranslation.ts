import { getLocales } from 'expo-localization';
import { resolveLocale, CATALOGUES } from '@dominion/localization';

export function useTranslation() {
  const locales = getLocales();
  const languageTag = locales.length > 0 ? locales[0].languageTag : undefined;
  const locale = resolveLocale(languageTag);
  
  const t = (key: string): string => {
    const value = key.split('.').reduce(
      (o: unknown, i: string) => (o && typeof o === 'object' && i in o ? (o as Record<string, unknown>)[i] : undefined),
      CATALOGUES[locale]
    );
    return typeof value === 'string' ? value : key;
  };
  
  return { t, locale };
}
