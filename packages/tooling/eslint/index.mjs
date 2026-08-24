// Shared ESLint configuration (flat config).
//
// The custom rules below are not style preferences — each one encodes an
// architectural decision that is otherwise invisible in review.

import js from '@eslint/js';
import tseslint from '@typescript-eslint/eslint-plugin';
import tsparser from '@typescript-eslint/parser';
import prettier from 'eslint-config-prettier';

export default [
  {
    ignores: [
      '**/node_modules/**',
      '**/dist/**',
      '**/.expo/**',
      '**/generated/**',
      '**/ios/**',
      '**/android/**',
      '**/vendor/**',
    ],
  },
  js.configs.recommended,
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      parser: tsparser,
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: { ecmaFeatures: { jsx: true } },
      globals: {
        console: 'readonly',
        process: 'readonly',
        fetch: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        __DEV__: 'readonly',
      },
    },
    plugins: { '@typescript-eslint': tseslint },
    rules: {
      ...tseslint.configs.recommended.rules,

      // TypeScript already reports unknown identifiers, and it knows the React
      // Native and DOM lib surfaces that ESLint does not. Leaving this on just
      // produces false positives on `Response`, `RequestInit` and friends.
      'no-undef': 'off',

      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/consistent-type-imports': [
        'error',
        { prefer: 'type-imports', fixStyle: 'inline-type-imports' },
      ],
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],

      eqeqeq: ['error', 'always'],
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'prefer-const': 'error',
      'no-var': 'error',

      // Server state belongs in TanStack Query, never a Zustand store.
      // docs/mobile/architecture.md — two sources of truth always drift.
      'no-restricted-imports': [
        'error',
        {
          paths: [
            {
              name: 'react-native',
              importNames: ['AsyncStorage'],
              message:
                'Use MMKV for cache and SecureStore for credentials. AsyncStorage is neither fast nor secure.',
            },
          ],
        },
      ],

      // Credentials live only in SecureStore/Keychain (ADR-011).
      'no-restricted-properties': [
        'error',
        {
          object: 'localStorage',
          message: 'Not available in React Native. Use MMKV or SecureStore.',
        },
      ],
    },
  },
  {
    // Build configuration files are CommonJS and run in Node.
    files: ['**/*.config.js', '**/babel.config.js', '**/metro.config.js'],
    languageOptions: {
      sourceType: 'commonjs',
      globals: { module: 'writable', require: 'readonly', __dirname: 'readonly' },
    },
  },
  {
    // CLI validators exist to print to stdout — that is their entire output
    // contract. Restricting console here would just push them to a worse
    // logging mechanism.
    files: ['**/validate.ts', '**/scripts/**/*.ts'],
    rules: {
      'no-console': 'off',
    },
  },
  prettier,
];
