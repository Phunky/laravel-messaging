<?php

namespace Phunky\LaravelMessaging\Builders;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\MessagingEventName;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Services\MessagingService;

class ConversationBuilder
{
    protected ?Conversation $resolvedConversation = null;

    /**
     * @param  array<int, Messageable>  $messageables
     */
    public function __construct(
        protected MessagingService $messaging,
        protected array $messageables,
        protected ?Messageable $actingAs = null,
    ) {}

    public function as(Messageable $messageable): static
    {
        $clone = clone $this;
        $clone->actingAs = $messageable;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function send(string $text, ?Messageable $sender = null, array $meta = []): Message
    {
        $sender ??= $this->actingAs;

        if (! $sender) {
            throw new InvalidArgumentException('A sender must be provided via send($text, $sender) or as($sender).');
        }

        if (! $this->resolvedConversation) {
            [$this->resolvedConversation] = $this->messaging->findOrCreateConversation(...$this->messageables);
        }

        return $this->messaging->sendMessage($this->resolvedConversation, $sender, $text, $meta);
    }

    public function messages(): CursorPaginator|LengthAwarePaginator
    {
        $conversation = $this->resolvedConversation ?? $this->messaging->findConversation(...$this->messageables);

        if (! $conversation instanceof Conversation) {
            return $this->messaging->emptyMessagesPagination();
        }

        return $this->messaging->paginateMessages($conversation);
    }

    public function unreadCount(): int
    {
        $reader = $this->actingAs;

        if (! $reader) {
            throw new InvalidArgumentException('A reader must be provided via as($messageable) for unreadCount().');
        }

        $conversation = $this->resolvedConversation ?? $this->messaging->findConversation(...$this->messageables);

        if (! $conversation instanceof Conversation) {
            return 0;
        }

        return $this->messaging->unreadCount($conversation, $reader);
    }

    public function markAllRead(): int
    {
        $reader = $this->actingAs;

        if (! $reader) {
            throw new InvalidArgumentException('A reader must be provided via as($messageable) for markAllRead().');
        }

        $conversation = $this->resolvedConversation ?? $this->messaging->findConversation(...$this->messageables);

        if (! $conversation instanceof Conversation) {
            return 0;
        }

        return $this->messaging->markAllRead($conversation, $reader);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordInviteAccepted(array $meta = []): MessagingEvent
    {
        $actor = $this->actingAs;

        if (! $actor) {
            throw new InvalidArgumentException('An actor must be provided via as($messageable) for recordInviteAccepted().');
        }

        $conversation = $this->resolvedConversation ?? $this->messaging->findConversation(...$this->messageables);

        if (! $conversation instanceof Conversation) {
            throw new InvalidArgumentException('No conversation exists for the given participants.');
        }

        return $this->messaging->recordEvent($conversation, $actor, MessagingEventName::ConversationInviteAccepted, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordMemberLeft(array $meta = []): MessagingEvent
    {
        $actor = $this->actingAs;

        if (! $actor) {
            throw new InvalidArgumentException('An actor must be provided via as($messageable) for recordMemberLeft().');
        }

        $conversation = $this->resolvedConversation ?? $this->messaging->findConversation(...$this->messageables);

        if (! $conversation instanceof Conversation) {
            throw new InvalidArgumentException('No conversation exists for the given participants.');
        }

        return $this->messaging->recordEvent($conversation, $actor, MessagingEventName::ConversationMemberLeft, $meta);
    }
}
