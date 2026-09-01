/**
 * API client.
 *
 * One place that knows how to talk to the server: correlation ids, the response
 * envelope, and error normalisation. Screens never call `fetch` directly.
 *
 * Auth token attachment and single-flight refresh arrive with GSD Phase 03.
 */

import Constants from 'expo-constants';
import { isApiError, type AuthTokens, type ErrorCode, type ErrorResponse } from '@castleroyale/contracts';
import { clearTokens, getTokens, saveTokens } from '@/features/auth/SecureStorage';

const extra = (Constants.expoConfig?.extra ?? {}) as { apiUrl?: string };

function hostFromHostUri(hostUri: string): string | null {
  if (hostUri.startsWith('[')) {
    const closingBracket = hostUri.indexOf(']');
    return closingBracket > 1 ? hostUri.slice(1, closingBracket) : null;
  }

  const separator = hostUri.lastIndexOf(':');
  return separator > 0 ? hostUri.slice(0, separator) : hostUri;
}

/**
 * A phone's localhost is the phone itself. During Expo LAN development, point
 * the local API at the same computer that serves the Metro bundle instead.
 */
export function resolveApiUrl(configuredUrl: string, hostUri?: string): string {
  if (!hostUri) return configuredUrl;

  const developmentHost = hostFromHostUri(hostUri);
  if (!developmentHost) return configuredUrl;

  try {
    const apiUrl = new URL(configuredUrl);
    const configuredHost = apiUrl.hostname.replace(/^\[|\]$/g, '');
    const isLoopback = ['localhost', '127.0.0.1', '::1'].includes(configuredHost);

    if (!isLoopback) return configuredUrl;

    apiUrl.hostname = developmentHost;
    return apiUrl.toString().replace(/\/$/, '');
  } catch {
    return configuredUrl;
  }
}

const configuredApiUrl =
  process.env['EXPO_PUBLIC_API_URL'] ?? extra.apiUrl ?? 'http://localhost:8080/api/v1';

export const API_URL = resolveApiUrl(configuredApiUrl, Constants.expoConfig?.hostUri);

/**
 * A failure the server described in its own vocabulary.
 *
 * Callers branch on `code`, never on `message` — messages are localised and
 * change freely (docs/api/api-guidelines.md).
 */
export class ApiError extends Error {
  readonly code: ErrorCode;
  readonly details: Record<string, unknown> | undefined;
  readonly retryable: boolean;
  readonly status: number;

  constructor(status: number, body: ErrorResponse) {
    super(body.error.message);
    this.name = 'ApiError';
    this.status = status;
    this.code = body.error.code;
    this.details = body.error.details;
    this.retryable = body.error.retryable;
  }
}

/** A failure that never reached the server, or that it could not describe. */
export class NetworkError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'NetworkError';
  }
}

function requestId(): string {
  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
}

function idempotencyKey(): string {
  const nativeUuid = globalThis.crypto?.randomUUID;
  if (nativeUuid) return nativeUuid.call(globalThis.crypto);

  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 18)}`;
}

let refreshPromise: Promise<string | null> | null = null;

async function refreshAccessToken(): Promise<string | null> {
  if (refreshPromise !== null) return refreshPromise;

  refreshPromise = (async () => {
    try {
      const { refresh } = await getTokens();
      if (!refresh) return null;

      const response = await fetch(`${API_URL}/auth/refresh`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'Idempotency-Key': idempotencyKey(),
          'X-Request-Id': requestId(),
        },
        body: JSON.stringify({ refresh_token: refresh }),
      });
      const body: unknown = await response.json().catch(() => null);
      if (!response.ok || !body || typeof body !== 'object' || !('data' in body)) return null;

      const tokens = (body as { data: AuthTokens }).data;
      await saveTokens(tokens.access_token, tokens.refresh_token);
      return tokens.access_token;
    } catch {
      return null;
    } finally {
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}

type ApiRequestOptions = {
  authenticated?: boolean;
  isRetry?: boolean;
};

async function send<TData>(path: string, init: RequestInit, options: ApiRequestOptions): Promise<TData> {
  let response: Response;
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  headers.set('Content-Type', 'application/json');
  headers.set('X-Request-Id', requestId());

  const method = (init.method ?? 'GET').toUpperCase();
  if ((method === 'POST' || method === 'DELETE') && !headers.has('Idempotency-Key')) {
    headers.set('Idempotency-Key', idempotencyKey());
  }

  if (options.authenticated) {
    const { access } = await getTokens();
    if (access) headers.set('Authorization', `Bearer ${access}`);
  }

  try {
    response = await fetch(`${API_URL}${path}`, {
      ...init,
      headers,
    });
  } catch (cause) {
    throw new NetworkError((cause as Error).message);
  }

  const body: unknown = await response.json().catch(() => null);

  if (response.status === 401 && options.authenticated && !options.isRetry && path !== '/auth/refresh') {
    const newAccess = await refreshAccessToken();
    if (newAccess) {
      headers.set('Authorization', `Bearer ${newAccess}`);
      return send<TData>(path, { ...init, headers }, { ...options, isRetry: true });
    }

    await clearTokens();
  }

  if (!response.ok) {
    if (isApiError(body)) throw new ApiError(response.status, body);
    throw new NetworkError(`Unexpected ${response.status} response`);
  }

  return (body as { data: TData }).data;
}

export function apiRequest<TData>(
  path: string,
  init: RequestInit = {},
  options: ApiRequestOptions = {},
): Promise<TData> {
  return send<TData>(path, init, options);
}

export function authenticatedRequest<TData>(path: string, init: RequestInit = {}): Promise<TData> {
  return apiRequest<TData>(path, init, { authenticated: true });
}
