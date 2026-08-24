<?php

declare(strict_types=1);

namespace Game\Shared\Application\Error;

/**
 * The machine-readable error catalogue.
 *
 * The mobile client branches on these codes only — never on the human message,
 * which is localised and may change at any time. Codes are additive: once
 * shipped, a code's meaning is frozen.
 *
 * @see docs/api/api-guidelines.md
 */
enum ErrorCode: string
{
    // --- Generic transport / validation --------------------------------------
    case ValidationFailed = 'VALIDATION_FAILED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case Conflict = 'CONFLICT';
    case RateLimited = 'RATE_LIMITED';
    case ServerError = 'SERVER_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case UnsupportedClientVersion = 'UNSUPPORTED_CLIENT_VERSION';

    // --- Idempotency ---------------------------------------------------------
    case IdempotencyKeyRequired = 'IDEMPOTENCY_KEY_REQUIRED';
    case IdempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
    case IdempotencyRequestInFlight = 'IDEMPOTENCY_REQUEST_IN_FLIGHT';

    // --- Identity ------------------------------------------------------------
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case AccountBanned = 'ACCOUNT_BANNED';
    case AccountSuspended = 'ACCOUNT_SUSPENDED';
    case DeviceSessionRevoked = 'DEVICE_SESSION_REVOKED';
    case TokenExpired = 'TOKEN_EXPIRED';
    case GuestUpgradeRequired = 'GUEST_UPGRADE_REQUIRED';

    // --- Economy -------------------------------------------------------------
    case InsufficientResources = 'INSUFFICIENT_RESOURCES';
    case WarehouseCapacityExceeded = 'WAREHOUSE_CAPACITY_EXCEEDED';
    case LedgerImbalance = 'LEDGER_IMBALANCE';

    // --- City / buildings ----------------------------------------------------
    case CityBusy = 'CITY_BUSY';
    case CityNotOwned = 'CITY_NOT_OWNED';
    case BuildingMaxLevel = 'BUILDING_MAX_LEVEL';
    case BuildingRequirementsNotMet = 'BUILDING_REQUIREMENTS_NOT_MET';
    case BuildQueueFull = 'BUILD_QUEUE_FULL';
    case BuildingSlotOccupied = 'BUILDING_SLOT_OCCUPIED';

    // --- Technology ----------------------------------------------------------
    case ResearchInProgress = 'RESEARCH_IN_PROGRESS';
    case TechnologyLocked = 'TECHNOLOGY_LOCKED';
    case TechnologyMaxLevel = 'TECHNOLOGY_MAX_LEVEL';

    // --- Army / marches ------------------------------------------------------
    case MarchLimitReached = 'MARCH_LIMIT_REACHED';
    case ArmyAlreadyDeployed = 'ARMY_ALREADY_DEPLOYED';
    case InsufficientTroops = 'INSUFFICIENT_TROOPS';
    case InvalidTarget = 'INVALID_TARGET';
    case TargetOutOfRange = 'TARGET_OUT_OF_RANGE';
    case MarchNotCancellable = 'MARCH_NOT_CANCELLABLE';
    case TroopCapacityExceeded = 'TROOP_CAPACITY_EXCEEDED';

    // --- Combat --------------------------------------------------------------
    case BattleAlreadyResolved = 'BATTLE_ALREADY_RESOLVED';
    case SimulationVersionMismatch = 'SIMULATION_VERSION_MISMATCH';

    // --- Protection rules ----------------------------------------------------
    case PlayerProtected = 'PLAYER_PROTECTED';
    case AttackerProtected = 'ATTACKER_PROTECTED';
    case PowerDifferenceTooLarge = 'POWER_DIFFERENCE_TOO_LARGE';
    case BeginnerZoneRestricted = 'BEGINNER_ZONE_RESTRICTED';

    // --- Alliance ------------------------------------------------------------
    case AlliancePermissionDenied = 'ALLIANCE_PERMISSION_DENIED';
    case AllianceFull = 'ALLIANCE_FULL';
    case AlreadyInAlliance = 'ALREADY_IN_ALLIANCE';
    case NotInAlliance = 'NOT_IN_ALLIANCE';
    case AllianceNameTaken = 'ALLIANCE_NAME_TAKEN';

    // --- Trade / market ------------------------------------------------------
    case MarketOrderUnavailable = 'MARKET_ORDER_UNAVAILABLE';
    case TradeRouteBusy = 'TRADE_ROUTE_BUSY';
    case TradeLimitReached = 'TRADE_LIMIT_REACHED';

    // --- World ---------------------------------------------------------------
    case TileOccupied = 'TILE_OCCUPIED';
    case TileNotSettleable = 'TILE_NOT_SETTLEABLE';
    case WorldClosed = 'WORLD_CLOSED';
    case WorldFull = 'WORLD_FULL';

    // --- Social / moderation -------------------------------------------------
    case ChatMuted = 'CHAT_MUTED';
    case ContentRejected = 'CONTENT_REJECTED';
    case RecipientBlocked = 'RECIPIENT_BLOCKED';

    // --- LiveOps / store -----------------------------------------------------
    case FeatureDisabled = 'FEATURE_DISABLED';
    case EventNotActive = 'EVENT_NOT_ACTIVE';
    case RewardAlreadyClaimed = 'REWARD_ALREADY_CLAIMED';
    case PurchaseVerificationFailed = 'PURCHASE_VERIFICATION_FAILED';

    /**
     * Default HTTP status for this code.
     *
     * Handlers may override, but the default keeps the surface consistent so
     * the client's retry policy can key off status alone.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::ValidationFailed => 422,
            self::Unauthenticated, self::TokenExpired, self::DeviceSessionRevoked => 401,
            self::Forbidden, self::AccountBanned, self::AccountSuspended,
            self::AlliancePermissionDenied, self::FeatureDisabled,
            self::GuestUpgradeRequired => 403,
            self::NotFound => 404,
            self::Conflict, self::IdempotencyKeyReused, self::BattleAlreadyResolved,
            self::RewardAlreadyClaimed, self::TileOccupied, self::AlreadyInAlliance,
            self::AllianceNameTaken => 409,
            self::IdempotencyRequestInFlight => 425,
            self::RateLimited => 429,
            self::ServiceUnavailable, self::WorldClosed => 503,
            self::ServerError, self::LedgerImbalance => 500,
            default => 400,
        };
    }

    /**
     * Whether the mobile client may safely retry the exact same request.
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::RateLimited, self::ServiceUnavailable,
            self::IdempotencyRequestInFlight, self::ServerError => true,
            default => false,
        };
    }
}
