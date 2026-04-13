<?php

namespace Phunky\LaravelMessaging\Tests\Fixtures;

use Phunky\LaravelMessaging\Models\Conversation;

class TestConversation extends Conversation
{
    public static function swappedModelProbe(): bool
    {
        return true;
    }
}
