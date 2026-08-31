<?php

declare(strict_types=1);

namespace Game\Identity\Interface\Http;

use Game\Identity\Application\AccountUpgrader;
use Game\Identity\Application\GuestLoginHandler;
use Game\Identity\Application\LoginHandler;
use Game\Identity\Application\SocialLoginHandler;
use Game\Identity\Application\TokenRefresher;
use Game\Identity\Domain\Account;
use Game\Identity\Domain\DeviceSession;
use Game\Identity\Domain\SocialIdentityVerifier;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Application\Idempotency\IdempotencyService;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Interface\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class AuthController
{
    public function __construct(
        private GuestLoginHandler $guest,
        private LoginHandler $login,
        private TokenRefresher $refresh,
        private SocialLoginHandler $social,
        private AccountUpgrader $upgrader,
        private SocialIdentityVerifier $socialVerifier,
        private IdempotencyService $idempotency,
        private Clock $clock,
    ) {}

    public function guest(Request $request): JsonResponse
    {
        return $this->idempotency->run($request, fn (): JsonResponse => ApiResponse::success(
            $this->guest->handle($this->device($request)),
            status: 201,
        ));
    }

    public function login(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ])->validate();

        return $this->idempotency->run($request, fn (): JsonResponse => ApiResponse::success(
            $this->login->handle(
                (string) $request->input('email'),
                (string) $request->input('password'),
                $this->device($request),
            ),
        ));
    }

    public function refresh(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'refresh_token' => ['required', 'string'],
        ])->validate();

        return $this->idempotency->run($request, fn (): JsonResponse => ApiResponse::success(
            $this->refresh->handle((string) $request->input('refresh_token'), $this->device($request)),
        ));
    }

    public function social(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'provider' => ['required', 'in:apple,google'],
            'identity_token' => ['required', 'string', 'max:8192'],
        ])->validate();

        return $this->idempotency->run($request, fn (): JsonResponse => ApiResponse::success(
            $this->social->handle(
                (string) $request->input('provider'),
                (string) $request->input('identity_token'),
                $this->device($request),
            ),
        ));
    }

    public function upgrade(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'email' => ['nullable', 'email', 'max:254', 'required_without:provider', 'unique:accounts,email'],
            'password' => ['nullable', 'string', 'min:8', 'required_with:email'],
            'provider' => ['nullable', 'in:apple,google', 'required_without:email'],
            'identity_token' => ['nullable', 'string', 'max:8192', 'required_with:provider'],
        ])->validate();

        return $this->idempotency->run($request, function () use ($request): JsonResponse {
            $account = $request->user();
            if (! $account instanceof Account) {
                throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
            }

            $provider = $request->input('provider');
            $providerId = null;
            if (is_string($provider)) {
                $settings = config('services.'.$provider);
                if (! is_array($settings) || ($settings['client_ids'] ?? []) === []) {
                    throw GameException::of(ErrorCode::FeatureDisabled, 'Provider not configured.');
                }

                $identity = $this->socialVerifier->verify($provider, (string) $request->input('identity_token'));
                $providerId = $identity['provider_id'];
            }

            $this->upgrader->upgrade(
                (string) $account->getKey(),
                is_string($request->input('email')) ? $request->input('email') : null,
                is_string($request->input('password')) ? $request->input('password') : null,
                is_string($provider) ? $provider : null,
                $providerId,
            );

            return ApiResponse::success([]);
        });
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->idempotency->run($request, function () use ($request): JsonResponse {
            $token = $request->user()?->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return ApiResponse::success([]);
        });
    }

    public function sessions(Request $request): JsonResponse
    {
        $accountId = $request->user()?->getAuthIdentifier();
        $sessionsQuery = DeviceSession::query()
            ->where('account_id', $accountId);
        $sessionsQuery->getQuery()->orderByDesc('last_seen_at');
        $sessions = $sessionsQuery->get()
            ->map(static fn (DeviceSession $session): array => [
                'id' => (string) $session->getKey(),
                'device_name' => (string) $session->device_name,
                'platform' => (string) $session->platform,
                'ip' => (string) $session->ip,
                'last_seen_at' => $session->last_seen_at?->toDateTimeImmutable()->format(DATE_ATOM),
                'revoked_at' => $session->revoked_at?->toDateTimeImmutable()->format(DATE_ATOM),
            ])
            ->values()
            ->all();

        return ApiResponse::success(array_values($sessions));
    }

    public function revokeSession(Request $request, string $id): JsonResponse
    {
        return $this->idempotency->run($request, function () use ($request, $id): JsonResponse {
            $accountId = $request->user()?->getAuthIdentifier();
            $session = DeviceSession::query()
                ->where('account_id', $accountId)
                ->whereKey($id)
                ->first();

            if ($session === null) {
                throw GameException::of(ErrorCode::NotFound, 'The session does not exist.');
            }

            $session->forceFill(['revoked_at' => $this->clock->now()])->save();

            return ApiResponse::success([]);
        });
    }

    /**
     * @return array{device_id: string, device_name: string, platform: string, ip: string}
     */
    private function device(Request $request): array
    {
        return [
            'device_id' => $this->header($request, 'X-Device-Id', 'unknown-device'),
            'device_name' => $this->header($request, 'X-Device-Name', 'Unknown device'),
            'platform' => $this->header($request, 'X-Client-Platform', 'unknown'),
            'ip' => (string) ($request->ip() ?? '0.0.0.0'),
        ];
    }

    private function header(Request $request, string $name, string $fallback): string
    {
        $value = $request->header($name);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
