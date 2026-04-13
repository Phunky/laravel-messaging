<?php

namespace Phunky\LaravelMessaging\Tests\Fixtures;

use Phunky\LaravelMessaging\Events\BroadcastableMessagingEvent;

final class ExtensionBroadcastProbeEvent extends BroadcastableMessagingEvent
{
    public function __construct(
        int|string $conversationId,
        public string $probe = 'ok',
    ) {
        parent::__construct($conversationId);
    }
}
