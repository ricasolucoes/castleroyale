/**
 * API client.
 *
 * One place that knows how to talk to the server: correlation ids, the response
 * envelope, and error normalisation. Screens never call `fetch` directly.
 *
 * Auth token attachment and single-flight refresh arrive with GSD Phase 03.
 */

import Constants from 'expo-constants';
import { isApiError, type ErrorCode, type ErrorResponse } from '@dominion/contracts';

const extra = (Constants.expoConfig?.extra ?? {}) as { apiUrl?: string };

export const API_URL = extra.apiUrl ?? 'http://localhost:8080/api/v1';

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

export async function apiRequest<TData>(
  path: string,
  init: RequestInit = {},
): Promise<TData> {
  let response: Response;

  try {
    response = await fetch(`${API_URL}${path}`, {
      ...init,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Request-Id': requestId(),
        ...init.headers,
      },
    });
  } catch (cause) {
    throw new NetworkError((cause as Error).message);
  }

  const body: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    if (isApiError(body)) throw new ApiError(response.status, body);
    throw new NetworkError(`Unexpected ${response.status} response`);
  }

  return (body as { data: TData }).data;
}
