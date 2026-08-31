import { isApiError, isRetryable } from '@dominion/contracts';
import { resolveApiUrl } from '../src/api/client';

describe('API error contract', () => {
  it('recognises the standard error envelope', () => {
    expect(
      isApiError({
        error: { code: 'INSUFFICIENT_RESOURCES', message: 'Not enough.', retryable: false },
      }),
    ).toBe(true);
  });

  it('does not mistake a success envelope for an error', () => {
    expect(isApiError({ data: { status: 'ok' } })).toBe(false);
  });

  it('agrees with the server about what is retryable', () => {
    // Mirrors ErrorCode::isRetryable() in PHP. A disagreement means the client
    // either gives up too early or hammers a permanent failure.
    expect(isRetryable('RATE_LIMITED')).toBe(true);
    expect(isRetryable('SERVER_ERROR')).toBe(true);
    expect(isRetryable('INSUFFICIENT_RESOURCES')).toBe(false);
    expect(isRetryable('VALIDATION_FAILED')).toBe(false);
  });
});

describe('development API URL', () => {
  it('replaces loopback with the computer serving Expo on a physical device', () => {
    expect(resolveApiUrl('http://localhost:8080/api/v1', '192.168.1.13:8099')).toBe(
      'http://192.168.1.13:8080/api/v1',
    );
  });

  it('keeps configured remote API URLs unchanged', () => {
    expect(resolveApiUrl('https://api.example.test/api/v1', '192.168.1.13:8099')).toBe(
      'https://api.example.test/api/v1',
    );
  });
});
