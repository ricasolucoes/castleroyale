---
wave: 3
depends_on: ["03-02", "03-03"]
files_modified:
  - apps/mobile/package.json
  - apps/mobile/src/features/auth/SecureStorage.ts
  - apps/mobile/src/api/apiClient.ts
  - apps/mobile/src/features/auth/components/LoginScreen.tsx
  - apps/mobile/src/features/auth/components/GuestUpgradeScreen.tsx
  - apps/mobile/src/app/_layout.tsx
autonomous: true
---

# Plan 03-04: Mobile auth flow, secure storage and session restoration

## Goal
Implement the client-side authentication flows, secure token storage using device biometrics, and single-flight token refresh logic.

## Requirements
- REQ-01, REQ-10, REQ-14

## Context
- Tokens MUST go in SecureStore/Keychain (expo-secure-store), NEVER in MMKV/AsyncStorage.
- Refresh must be single-flight.
- Native biometrics integration for secure storage.

## Tasks

<task>
<read_first>
- apps/mobile/package.json
- apps/mobile/src/features/auth/SecureStorage.ts
</read_first>
<action>
Install `expo-secure-store` and `expo-local-authentication` in `apps/mobile`.
Create `apps/mobile/src/features/auth/SecureStorage.ts` implementing `saveTokens(access, refresh)`, `getTokens()`, `clearTokens()`.
Configure `expo-secure-store` to use `LocalAuthentication` (require biometrics/passcode to read tokens).
Write a Jest test asserting tokens are stored securely.
</action>
<acceptance_criteria>
- `package.json` contains `expo-secure-store`
- `SecureStorage.ts` exports `saveTokens` and `getTokens`
- Tests pass when running `npm test`
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/mobile/src/api/apiClient.ts
</read_first>
<action>
Update Axios/fetch interceptor in `apps/mobile/src/api/apiClient.ts`.
Configure a request interceptor to generate and attach an `Idempotency-Key` header (e.g., using `uuid` or `ulid`) for all `POST` and `DELETE` requests.
On 401 response (or custom ErrorCode `TOKEN_EXPIRED`), intercept it.
Implement a single-flight mutex/promise for the refresh call so multiple parallel 401s only trigger one `/api/v1/auth/refresh` request.
If refresh fails with `TOKEN_EXPIRED`, clear `SecureStorage` and redirect to Login screen.
</action>
<acceptance_criteria>
- `apiClient.ts` contains 401 interceptor logic
- `apiClient.ts` injects `Idempotency-Key` on POST/DELETE requests
- `apiClient.ts` contains single-flight promise logic for refreshing
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/mobile/src/features/auth/components/LoginScreen.tsx
- apps/mobile/src/features/auth/components/GuestUpgradeScreen.tsx
</read_first>
<action>
Create `apps/mobile/src/features/auth/components/LoginScreen.tsx` with "Play as Guest", "Sign in with Apple", "Sign in with Google", and "Email Sign In" buttons.
Create `apps/mobile/src/features/auth/components/GuestUpgradeScreen.tsx` to allow guests to bind an email or social account.
Use generated OpenAPI client hooks/functions to make the API calls.
</action>
<acceptance_criteria>
- `LoginScreen.tsx` exists and exports `LoginScreen`
- `GuestUpgradeScreen.tsx` exists and exports `GuestUpgradeScreen`
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/mobile/src/app/_layout.tsx
</read_first>
<action>
Implement session restoration in `apps/mobile/src/app/_layout.tsx`.
On app start, attempt to read tokens from `SecureStorage`.
If valid access token exists, navigate to main shell.
If expired access token but refresh token exists, attempt refresh.
If neither, navigate to `LoginScreen`.
</action>
<acceptance_criteria>
- `_layout.tsx` reads from `SecureStorage` on mount
</acceptance_criteria>
</task>

## Verification
- `npm run typecheck`
- `npm test`

## Must Haves
- Credentials only in SecureStore.
- Biometric integration for token access.
- Single-flight token refresh.
