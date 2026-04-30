<?php

namespace Phunky\LaravelMessaging\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Phunky\LaravelMessaging\Concerns\InteractsWithMessagingBroadcast;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Models\Conversation;

class AllMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithMessagingBroadcast, InteractsWithSockets, SerializesModels;

    public const BROADCAST_NAME = 'messaging.all_messages.read';

    public function __construct(
        public Conversation $conversation,
        public Messageable $reader,
        public int $count,
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
     * @return array{conversation_id: int|string, reader_type: string, reader_id: int|string, count: int}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->getKey(),
            'reader_type' => $this->reader->getMorphClass(),
            'reader_id' => $this->reader->getKey(),
            'count' => $this->count,
        ];
    }
}
