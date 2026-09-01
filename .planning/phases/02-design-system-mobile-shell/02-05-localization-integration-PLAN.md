---
wave: 5
depends_on: ["02-04-navigation-shell"]
files_modified:
  - apps/mobile/package.json
  - packages/localization/locales/en/navigation.json
  - packages/localization/locales/es/navigation.json
  - packages/localization/locales/pt-BR/navigation.json
  - packages/localization/src/index.ts
  - apps/mobile/src/i18n/useTranslation.ts
  - apps/mobile/app/(tabs)/_layout.tsx
  - apps/mobile/app/(tabs)/city.tsx
  - apps/mobile/app/(tabs)/world.tsx
  - apps/mobile/app/(tabs)/military.tsx
  - apps/mobile/app/(tabs)/alliance.tsx
  - apps/mobile/app/(tabs)/profile.tsx
autonomous: true
---

# Phase 02-05: Localization Integration

<must_haves>
  - The mobile shell is fully localized in pt-BR, en, and es.
  - No tab titles or screen headings in the navigation shell are hardcoded.
  - The `@castleroyale/localization` package is properly integrated into `apps/mobile`.
</must_haves>

<task>
  <id>1</id>
  <description>Create JSON catalogues for navigation</description>
  <read_first>
    - packages/localization/src/validate.ts
  </read_first>
  <action>
    Create `packages/localization/locales/en/navigation.json`, `packages/localization/locales/es/navigation.json`, and `packages/localization/locales/pt-BR/navigation.json`.
    The `en` content must be:
    {
      "tabs": {
        "city": "City",
        "world": "World",
        "military": "Military",
        "alliance": "Alliance",
        "profile": "Profile"
      }
    }
    The `es` content must translate these to:
    {
      "tabs": {
        "city": "Ciudad",
        "world": "Mundo",
        "military": "Militar",
        "alliance": "Alianza",
        "profile": "Perfil"
      }
    }
    The `pt-BR` content must translate these to:
    {
      "tabs": {
        "city": "Cidade",
        "world": "Mundo",
        "military": "Militar",
        "alliance": "Aliança",
        "profile": "Perfil"
      }
    }
  </action>
  <acceptance_criteria>
    - `npm run validate` inside `packages/localization` exits with 0.
    - `grep -r "City" packages/localization/locales/en/navigation.json` returns a match.
  </acceptance_criteria>
</task>

<task>
  <id>2</id>
  <description>Export catalogues and wire mobile dependency</description>
  <read_first>
    - packages/localization/src/index.ts
    - apps/mobile/package.json
  </read_first>
  <action>
    Update `packages/localization/src/index.ts` to export a new `const CATALOGUES` which statically imports the loaded catalogue objects for 'en', 'es', and 'pt-BR' by importing the `navigation.json` files.
    Example: `import enNavigation from '../locales/en/navigation.json'; export const CATALOGUES = { en: { navigation: enNavigation }, es: { navigation: esNavigation }, 'pt-BR': { navigation: ptBRNavigation } };`
    Update `apps/mobile/package.json` to include `"@castleroyale/localization": "*"` in dependencies.
  </action>
  <acceptance_criteria>
    - `grep "CATALOGUES" packages/localization/src/index.ts` returns a match.
    - `grep "@castleroyale/localization" apps/mobile/package.json` returns a match.
  </acceptance_criteria>
</task>

<task>
  <id>3</id>
  <description>Create useTranslation hook</description>
  <read_first>
    - packages/localization/src/index.ts
  </read_first>
  <action>
    Create `apps/mobile/src/i18n/useTranslation.ts`.
    It must export a `useTranslation` hook that uses `expo-localization` (`getLocales()[0].languageTag`) and `resolveLocale` from `@castleroyale/localization` to determine the current locale.
    It must return a `t(key: string)` function that resolves keys like `navigation.tabs.city` from `CATALOGUES`.
    Include a simple dot-notation resolver: `key.split('.').reduce((o: any, i: string) => o?.[i], CATALOGUES[locale]) ?? key`.
  </action>
  <acceptance_criteria>
    - The file `apps/mobile/src/i18n/useTranslation.ts` exists.
    - `grep "useTranslation" apps/mobile/src/i18n/useTranslation.ts` returns a match.
  </acceptance_criteria>
</task>

<task>
  <id>4</id>
  <description>Replace hardcoded strings in navigation shell</description>
  <read_first>
    - apps/mobile/app/(tabs)/_layout.tsx
    - apps/mobile/app/(tabs)/city.tsx
    - apps/mobile/app/(tabs)/world.tsx
    - apps/mobile/app/(tabs)/military.tsx
    - apps/mobile/app/(tabs)/alliance.tsx
    - apps/mobile/app/(tabs)/profile.tsx
  </read_first>
  <action>
    Update `apps/mobile/app/(tabs)/_layout.tsx` to use `const { t } = useTranslation();` and replace hardcoded tab titles with `t('navigation.tabs.city')`, `t('navigation.tabs.world')`, etc.
    Do the same for all 5 tab screens, replacing the `<Text variant="display">...</Text>` hardcoded strings with `t('navigation.tabs.X')` where X is the tab name.
  </action>
  <acceptance_criteria>
    - `grep -r "title: 'City'" apps/mobile/app/\(tabs\)` returns no matches.
    - `grep "t('navigation.tabs" apps/mobile/app/\(tabs\)/_layout.tsx` returns matches.
  </acceptance_criteria>
</task>

<verification>
  - All tasks completed successfully.
  - `npm run typecheck` in monorepo root exits with 0.
  - `npm run lint` in monorepo root exits with 0.
  - `cd apps/mobile && npm test` passes.
</verification>
