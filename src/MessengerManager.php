<?php

namespace Phunky\LaravelMessaging;

use Illuminate\Database\Eloquent\Builder;
use Phunky\LaravelMessaging\Builders\ConversationBuilder;
use Phunky\LaravelMessaging\Builders\MessageBuilder;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessaging\Testing\FakeMessenger;

class MessengerManager
{
    public function __construct(
        protected MessagingService $messaging,
    ) {}

    public function conversation(Messageable ...$messageables): ConversationBuilder
    {
        return new ConversationBuilder($this->messaging, $messageables);
    }

    public function message(int|string $id): MessageBuilder
    {
        return new MessageBuilder($this->messaging, $id);
    }

    /**
     * @return Builder<Conversation>
     */
    public function conversationsFor(Messageable $messageable): Builder
    {
        return $this->messaging->conversationsFor($messageable);
    }

    public static function fake(): FakeMessenger
    {
        return FakeMessenger::swap();
    }
}
