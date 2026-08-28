import { readFileSync } from 'node:fs';

describe('authentication storage boundary', () => {
  it('keeps credentials in SecureStore and protects reads with biometrics', () => {
    const source = readFileSync('src/features/auth/SecureStorage.ts', 'utf8');

    expect(source).toContain("from 'expo-secure-store'");
    expect(source).toContain("from 'expo-local-authentication'");
    expect(source).toContain('export async function saveTokens');
    expect(source).toContain('export async function getTokens');
    expect(source).not.toContain('AsyncStorage');
    expect(source).not.toContain('MMKV');
  });
});
