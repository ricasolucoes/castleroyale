import { isApiError, isRetryable } from '@dominion/contracts';

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
