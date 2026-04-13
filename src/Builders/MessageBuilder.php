<?php

namespace Phunky\LaravelMessaging\Builders;

use InvalidArgumentException;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Services\MessagingService;

class MessageBuilder
{
    protected ?Messageable $actingAs = null;

    protected ?Message $resolvedMessage = null;

    public function __construct(
        protected MessagingService $messaging,
        protected int|string $messageId,
    ) {}

    public function as(Messageable $messageable): static
    {
        $clone = clone $this;
        $clone->actingAs = $messageable;

        return $clone;
    }

    public function edit(string $newText): Message
    {
        $this->resolvedMessage = $this->messaging->editMessage($this->message(), $this->actor(), $newText);

        return $this->resolvedMessage;
    }

    public function delete(): void
    {
        $this->messaging->deleteMessage($this->message(), $this->actor());
        $this->resolvedMessage = null;
    }

    public function received(): MessagingEvent
    {
        return $this->messaging->markReceived($this->message(), $this->actor());
    }

    public function read(): MessagingEvent
    {
        return $this->messaging->markRead($this->message(), $this->actor());
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordEvent(string $event, array $meta = []): MessagingEvent
    {
        return $this->messaging->recordEvent($this->message(), $this->actor(), $event, $meta);
    }

    protected function message(): Message
    {
        return $this->resolvedMessage ??= $this->messaging->findMessage($this->messageId);
    }

    protected function actor(): Messageable
    {
        if (! $this->actingAs) {
            throw new InvalidArgumentException('An actor must be provided via as($messageable).');
        }

        return $this->actingAs;
    }
}
