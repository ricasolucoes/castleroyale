/**
 * The API contract shared by the backend and the mobile client.
 *
 * `openapi.yaml` is the source of truth; `src/generated/api.ts` is produced
 * from it by `npm run contracts:generate` and must never be hand-edited.
 * CI runs `contracts:check`, which regenerates and fails on any diff (ADR-017).
 */

export type { components, paths, operations } from './generated/api.ts';

import type { components } from './generated/api.ts';

export type ErrorCode = components['schemas']['ErrorCode'];
export type ErrorResponse = components['schemas']['ErrorResponse'];
export type ResourceBundle = components['schemas']['ResourceBundle'];
export type ResourceType = components['schemas']['ResourceType'];
export type ContentVersions = components['schemas']['ContentVersions'];
export type Pagination = components['schemas']['Pagination'];
export type Ulid = components['schemas']['Ulid'];

/** The success envelope. Every endpoint returns this or an {@link ErrorResponse}. */
export type ApiSuccess<TData, TMeta = Record<string, unknown>> = {
  data: TData;
  meta?: TMeta;
};

export type ApiResult<TData, TMeta = Record<string, unknown>> =
  | ApiSuccess<TData, TMeta>
  | ErrorResponse;

export function isApiError(value: unknown): value is ErrorResponse {
  return (
    typeof value === 'object' &&
    value !== null &&
    'error' in value &&
    typeof (value as ErrorResponse).error?.code === 'string'
  );
}

/**
 * Codes the client may safely retry with an identical request.
 *
 * Mirrors `ErrorCode::isRetryable()` on the server. Keep the two in step — a
 * disagreement means the client either gives up too early or hammers a failure.
 */
const RETRYABLE = new Set<ErrorCode>([
  'RATE_LIMITED',
  'SERVICE_UNAVAILABLE',
  'IDEMPOTENCY_REQUEST_IN_FLIGHT',
  'SERVER_ERROR',
]);

export function isRetryable(code: ErrorCode): boolean {
  return RETRYABLE.has(code);
}
