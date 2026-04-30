<?php

namespace Phunky\LaravelMessaging\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Phunky\LaravelMessaging\Concerns\InteractsWithMessagingBroadcast;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Participant;

class ConversationCreated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithMessagingBroadcast, InteractsWithSockets, SerializesModels;

    public const BROADCAST_NAME = 'messaging.conversation.created';

    /**
     * @param  Collection<int, Participant>  $participants
     */
    public function __construct(
        public Conversation $conversation,
        public Collection $participants,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return $this->messagingBroadcastChannels($this->conversation);
    }

    public function broadcastAs(): string
    {
        return self::BROADCAST_NAME;
    }

    /**
     * @return array{conversation_id: int|string, participant_ids: list<int|string>}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->getKey(),
            'participant_ids' => $this->participants
                ->map(fn (Participant $participant): int|string => $participant->getKey())
                ->values()
                ->all(),
        ];
    }
}
