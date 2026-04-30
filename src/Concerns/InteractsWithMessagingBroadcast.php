<?php

namespace Phunky\LaravelMessaging\Concerns;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Phunky\LaravelMessaging\Models\Conversation;

trait InteractsWithMessagingBroadcast
{
    public function broadcastWhen(): bool
    {
        return (bool) config('messaging.broadcasting.enabled');
    }

    /**
     * Conversations broadcast on presence channels so clients can derive
     * online participants (channel members) and exchange ephemeral client
     * events (e.g. `typing`) without any server-side dispatch.
     *
     * Host applications must implement the presence authorizer for
     * `{channel_prefix}.conversation.{conversationId}` and return an
     * associative array with at minimum an `id` and a `name` field.
     *
     * @return array<int, Channel>
     */
    protected function messagingBroadcastChannels(Conversation|int|string $conversation): array
    {
        $id = $this->messagingConversationId($conversation);
        $prefix = (string) config('messaging.broadcasting.channel_prefix', 'messaging');

        return [new PresenceChannel("{$prefix}.conversation.{$id}")];
    }

    protected function messagingConversationId(Conversation|int|string $conversation): int|string
    {
        return $conversation instanceof Conversation ? $conversation->getKey() : $conversation;
    }
}
