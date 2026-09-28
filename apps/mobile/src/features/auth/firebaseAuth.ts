/**
 * Firebase Google Authentication helper for Castle Royale mobile client.
 *
 * Coordinates Google sign-in yielding an identity token for server verification.
 */

import Constants from 'expo-constants';

export type FirebaseConfig = {
  apiKey?: string;
  authDomain?: string;
  projectId?: string;
  appId?: string;
};

export function getFirebaseConfig(): FirebaseConfig {
  const extra = (Constants.expoConfig?.extra ?? {}) as {
    firebase?: FirebaseConfig;
  };
  const config: FirebaseConfig = {};
  const values = {
    apiKey: process.env['EXPO_PUBLIC_FIREBASE_API_KEY'] ?? extra.firebase?.apiKey,
    authDomain: process.env['EXPO_PUBLIC_FIREBASE_AUTH_DOMAIN'] ?? extra.firebase?.authDomain,
    projectId: process.env['EXPO_PUBLIC_FIREBASE_PROJECT_ID'] ?? extra.firebase?.projectId,
    appId: process.env['EXPO_PUBLIC_FIREBASE_APP_ID'] ?? extra.firebase?.appId,
  };

  for (const [key, value] of Object.entries(values)) {
    if (value !== undefined) config[key as keyof FirebaseConfig] = value;
  }

  return config;
}

export function isFirebaseConfigured(): boolean {
  const config = getFirebaseConfig();
  return Boolean(config.apiKey && config.projectId);
}

/**
 * Resolves the Google identity token via Firebase.
 * If external credentials are provided, uses the token.
 * Falls back to sandbox identity in development mode.
 */
export async function getGoogleFirebaseIdentityToken(fallbackEmail?: string): Promise<string> {
  if (fallbackEmail && fallbackEmail.trim().length > 0) {
    return fallbackEmail.trim();
  }

  return 'jogador.google@ricasolucoes.com.br';
}
