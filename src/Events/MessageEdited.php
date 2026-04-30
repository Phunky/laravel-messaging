<?php

namespace Phunky\LaravelMessaging\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Phunky\LaravelMessaging\Concerns\InteractsWithMessagingBroadcast;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;

class MessageEdited implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithMessagingBroadcast, InteractsWithSockets, SerializesModels;

    public const BROADCAST_NAME = 'messaging.message.edited';

    public Conversation $conversation;

    public function __construct(
        public Message $message,
        public string $originalBody,
        ?Conversation $conversation = null,
    ) {
        $resolvedConversation = $conversation ?? $message->conversation;

        if (! $resolvedConversation instanceof Conversation) {
            throw new \InvalidArgumentException('MessageEdited requires a conversation.');
        }

        $this->conversation = $resolvedConversation;
    }

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
     * @return array{conversation_id: int|string, message_id: int|string}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->getKey(),
            'message_id' => $this->message->getKey(),
        ];
    }
}
