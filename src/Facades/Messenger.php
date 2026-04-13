<?php

namespace Phunky\LaravelMessaging\Facades;

use Illuminate\Support\Facades\Facade;
use Phunky\LaravelMessaging\Builders\ConversationBuilder;
use Phunky\LaravelMessaging\Builders\MessageBuilder;
use Phunky\LaravelMessaging\MessengerManager;
use Phunky\LaravelMessaging\Testing\FakeMessenger;

/**
 * @method static ConversationBuilder conversation(\Phunky\LaravelMessaging\Contracts\Messageable ...$messageables)
 * @method static MessageBuilder message(int|string $id)
 * @method static \Illuminate\Database\Eloquent\Builder<\Phunky\LaravelMessaging\Models\Conversation> conversationsFor(\Phunky\LaravelMessaging\Contracts\Messageable $messageable)
 * @method static FakeMessenger fake()
 *
 * @see MessengerManager
 */
class Messenger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MessengerManager::class;
    }
}
