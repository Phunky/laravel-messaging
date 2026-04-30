<?php

namespace Phunky\LaravelMessaging\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Participant;

class ConversationInboxUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const BROADCAST_NAME = 'messaging.inbox.updated';

    public function __construct(
        public Conversation $conversation,
        public ?string $activityType = null,
    ) {}

    public function broadcastWhen(): bool
    {
        return (bool) config('messaging.broadcasting.enabled');
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $pattern = config('messaging.broadcasting.inbox_channel_pattern');

        if (! is_string($pattern) || $pattern === '') {
            return [];
        }

        $this->conversation->loadMissing('participants.messageable');

        return $this->conversation->participants
            ->map(fn (Participant $participant): ?PrivateChannel => $this->channelForParticipant($participant, $pattern))
            ->filter()
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return self::BROADCAST_NAME;
    }

    /**
     * @return array{conversation_id: int|string, activity_type: ?string, last_activity_at: ?string}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->getKey(),
            'activity_type' => $this->activityType,
            'last_activity_at' => $this->conversation->last_activity_at?->toJSON(),
        ];
    }

    private function channelForParticipant(Participant $participant, string $pattern): ?PrivateChannel
    {
        $messageable = $participant->messageable;

        if ($messageable === null) {
            return null;
        }

        $type = $messageable->getMorphClass();

        return new PrivateChannel(strtr($pattern, [
            '{type}' => str_replace('\\', '.', $type),
            '{id}' => (string) $messageable->getKey(),
        ]));
    }
}
