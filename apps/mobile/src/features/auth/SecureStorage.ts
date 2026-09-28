import * as SecureStore from 'expo-secure-store';
import * as LocalAuthentication from 'expo-local-authentication';

const ACCESS_TOKEN_KEY = 'secure_access_token';
const REFRESH_TOKEN_KEY = 'secure_refresh_token';
const TOKEN_PRESENCE_KEY = 'secure_tokens_present';
const TOKEN_PRESENCE_VALUE = '1';

type Tokens = { access: string | null; refresh: string | null };

let memoryCache: Tokens | undefined;

async function isSecureStoreAvailable(): Promise<boolean> {
  try {
    return await SecureStore.isAvailableAsync();
  } catch {
    return false;
  }
}

async function authenticate(): Promise<boolean> {
  const hasHardware = await LocalAuthentication.hasHardwareAsync();
  const isEnrolled = hasHardware && (await LocalAuthentication.isEnrolledAsync());
  if (!isEnrolled) return true;

  const result = await LocalAuthentication.authenticateAsync();
  return result.success;
}

export async function saveTokens(access: string, refresh: string): Promise<void> {
  if (!(await isSecureStoreAvailable())) {
    // Web has no native SecureStore implementation. Keep the preview usable
    // without persisting credentials outside the device-native stores.
    memoryCache = { access, refresh };
    return;
  }

  await SecureStore.setItemAsync(ACCESS_TOKEN_KEY, access);
  await SecureStore.setItemAsync(REFRESH_TOKEN_KEY, refresh);
  // The marker is not a credential; it lets a fresh install skip an unnecessary
  // biometric prompt before there is anything protected to read.
  await SecureStore.setItemAsync(TOKEN_PRESENCE_KEY, TOKEN_PRESENCE_VALUE);
  memoryCache = { access, refresh };
}

export async function getTokens(): Promise<Tokens> {
  if (memoryCache !== undefined) return memoryCache;

  if (!(await isSecureStoreAvailable())) {
    memoryCache = { access: null, refresh: null };
    return memoryCache;
  }

  const tokenMarker = await SecureStore.getItemAsync(TOKEN_PRESENCE_KEY);
  if (tokenMarker !== TOKEN_PRESENCE_VALUE) {
    memoryCache = { access: null, refresh: null };
    return memoryCache;
  }

  const isAuthenticated = await authenticate();
  if (!isAuthenticated) {
    memoryCache = { access: null, refresh: null };
    return memoryCache;
  }

  const access = await SecureStore.getItemAsync(ACCESS_TOKEN_KEY);
  const refresh = await SecureStore.getItemAsync(REFRESH_TOKEN_KEY);
  memoryCache = { access, refresh };
  return memoryCache;
}

export async function clearTokens(): Promise<void> {
  if (await isSecureStoreAvailable()) {
    await SecureStore.deleteItemAsync(ACCESS_TOKEN_KEY);
    await SecureStore.deleteItemAsync(REFRESH_TOKEN_KEY);
    await SecureStore.deleteItemAsync(TOKEN_PRESENCE_KEY);
  }
  memoryCache = { access: null, refresh: null };
}
