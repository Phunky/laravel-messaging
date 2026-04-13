<?php

namespace Phunky\LaravelMessaging\Concerns;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Phunky\LaravelMessaging\Models\Conversation;

trait InteractsWithMessagingBroadcast
{
    public function broadcastWhen(): bool
    {
        return (bool) config('messaging.broadcasting.enabled');
    }

    /**
     * @return array<int, Channel>
     */
    protected function messagingBroadcastChannels(Conversation|int|string $conversation): array
    {
        $id = $conversation instanceof Conversation ? $conversation->getKey() : $conversation;
        $prefix = (string) config('messaging.broadcasting.channel_prefix', 'messaging');

        return [new PrivateChannel("{$prefix}.conversation.{$id}")];
    }
}
