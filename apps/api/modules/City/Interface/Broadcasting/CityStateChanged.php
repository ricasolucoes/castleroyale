<?php

declare(strict_types=1);

namespace Game\City\Interface\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A fact: this city's persisted state moved.
 *
 * Identifiers only. The client refetches over HTTP — a full entity in the
 * payload would be a second source of truth (docs/realtime/events.md).
 */
final class CityStateChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $cityId,
        public readonly string $worldId,
        public readonly string $occurredAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('city.'.$this->cityId);
    }

    public function broadcastAs(): string
    {
        return 'city.state_changed';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'city_id' => $this->cityId,
            'world_id' => $this->worldId,
            'occurred_at' => $this->occurredAt,
        ];
    }

    /** Fan-out never blocks the request that caused it. */
    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
