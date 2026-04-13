<?php

namespace Phunky\LaravelMessaging\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Phunky\LaravelMessaging\Concerns\InteractsWithMessagingBroadcast;
use Phunky\LaravelMessaging\Models\Message;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithMessagingBroadcast, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $originalBody,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return $this->messagingBroadcastChannels($this->message->getAttribute('conversation_id'));
    }

    public function broadcastAs(): string
    {
        return 'messaging.message.edited';
    }
}
