import { getLocales } from 'expo-localization';
import { resolveLocale, CATALOGUES } from '@dominion/localization';

export function useTranslation() {
  const locales = getLocales();
  const languageTag = locales.length > 0 ? locales[0].languageTag : undefined;
  const locale = resolveLocale(languageTag);
  
  const t = (key: string): string => {
    return key.split('.').reduce((o: any, i: string) => o?.[i], CATALOGUES[locale]) ?? key;
  };
  
  return { t, locale };
}
