<?php

namespace Phunky\LaravelMessaging\Testing;

use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Assert as PHPUnit;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Facades\Messenger;
use Phunky\LaravelMessaging\MessengerManager;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;

class FakeMessenger
{
    /** @var array<int, array{conversation: Conversation, participants: list<Messageable>}> */
    public array $conversationsCreated = [];

    /** @var array<int, array{message: Message, conversation: Conversation}> */
    public array $messagesSent = [];

    /** @var array<int, array{messageId: int|string, participantKey: string}> */
    public array $receiptsReceived = [];

    /** @var array<int, array{messageId: int|string, participantKey: string}> */
    public array $receiptsRead = [];

    public static function swap(): self
    {
        $fake = new self;

        App::instance(self::class, $fake);

        App::forgetInstance(MessagingService::class);
        App::forgetInstance(MessengerManager::class);

        App::bind(MessagingService::class, fn () => new FakeMessagingService($fake));

        App::singleton(MessengerManager::class, fn ($app) => new MessengerManager(
            $app->make(MessagingService::class)
        ));

        Messenger::clearResolvedInstances();

        return $fake;
    }

    /**
     * @param  callable(Message, Conversation):bool|null  $callback
     */
    public function assertMessageSent(?callable $callback = null): void
    {
        PHPUnit::assertNotEmpty($this->messagesSent, 'Expected a message to be sent, but none was sent.');

        if ($callback === null) {
            return;
        }

        $matching = collect($this->messagesSent)->first(function (array $row) use ($callback): bool {
            /** @var Message $message */
            $message = $row['message'];
            /** @var Conversation $conversation */
            $conversation = $row['conversation'];

            return $callback($message, $conversation);
        });

        PHPUnit::assertNotNull($matching, 'A matching sent message was not found.');
    }

    /**
     * @param  callable(Message, Conversation):bool|null  $callback
     */
    public function assertMessageNotSent(?callable $callback = null): void
    {
        if ($callback === null) {
            PHPUnit::assertEmpty($this->messagesSent, 'Unexpected messages were sent.');

            return;
        }

        $matching = collect($this->messagesSent)->first(function (array $row) use ($callback): bool {
            /** @var Message $message */
            $message = $row['message'];
            /** @var Conversation $conversation */
            $conversation = $row['conversation'];

            return $callback($message, $conversation);
        });

        PHPUnit::assertNull($matching, 'A matching sent message was found.');
    }

    public function assertConversationCreated(): void
    {
        PHPUnit::assertNotEmpty($this->conversationsCreated, 'Expected a conversation to be created, but none was created.');
    }

    public function assertConversationNotCreated(): void
    {
        PHPUnit::assertEmpty($this->conversationsCreated, 'Unexpected conversations were created.');
    }

    public function assertReceiptMarkedReceived(int|string $messageId, string $participantKey): void
    {
        $found = collect($this->receiptsReceived)->contains(fn (array $row): bool => $row['messageId'] === $messageId
            && $row['participantKey'] === $participantKey);

        PHPUnit::assertTrue($found, 'Expected receipt was not marked as received.');
    }

    public function assertReceiptMarkedRead(int|string $messageId, string $participantKey): void
    {
        $found = collect($this->receiptsRead)->contains(fn (array $row): bool => $row['messageId'] === $messageId
            && $row['participantKey'] === $participantKey);

        PHPUnit::assertTrue($found, 'Expected receipt was not marked as read.');
    }

    /**
     * @param  list<Messageable>  $participants
     */
    public function recordConversationCreated(Conversation $conversation, array $participants): void
    {
        $this->conversationsCreated[] = [
            'conversation' => $conversation,
            'participants' => $participants,
        ];
    }

    public function recordMessageSent(Message $message, Conversation $conversation): void
    {
        $this->messagesSent[] = [
            'message' => $message,
            'conversation' => $conversation,
        ];
    }

    public function recordReceiptReceived(int|string $messageId, string $participantKey): void
    {
        $this->receiptsReceived[] = [
            'messageId' => $messageId,
            'participantKey' => $participantKey,
        ];
    }

    public function recordReceiptRead(int|string $messageId, string $participantKey): void
    {
        $this->receiptsRead[] = [
            'messageId' => $messageId,
            'participantKey' => $participantKey,
        ];
    }
}
