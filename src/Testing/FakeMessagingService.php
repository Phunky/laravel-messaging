<?php

namespace Phunky\LaravelMessaging\Testing;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator as CursorPaginatorConcrete;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Events\AllMessagesRead;
use Phunky\LaravelMessaging\Events\ConversationCreated;
use Phunky\LaravelMessaging\Events\MessageDeleted;
use Phunky\LaravelMessaging\Events\MessageEdited;
use Phunky\LaravelMessaging\Events\MessageRead;
use Phunky\LaravelMessaging\Events\MessageReceived;
use Phunky\LaravelMessaging\Events\MessageSending;
use Phunky\LaravelMessaging\Events\MessageSent;
use Phunky\LaravelMessaging\Exceptions\CannotMessageException;
use Phunky\LaravelMessaging\Exceptions\MessageRejectedException;
use Phunky\LaravelMessaging\MessagingEventName;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Models\Participant;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessaging\Support\ParticipantHash;

class FakeMessagingService extends MessagingService
{
    /** @var array<string, Conversation> */
    protected array $conversations = [];

    /** @var array<int|string, Message> */
    protected array $messages = [];

    /** @var array<string, MessagingEvent> */
    protected array $events = [];

    protected int $nextConversationId = 1;

    protected int $nextMessageId = 1;

    protected int $nextParticipantId = 1;

    protected int $nextMessagingEventId = 1;

    /** @var array<string, array<int, Participant>> */
    protected array $participantsByConversationKey = [];

    public function __construct(
        protected FakeMessenger $fake,
    ) {}

    /**
     * @return array{0: Conversation, 1: bool}
     */
    public function findOrCreateConversation(Messageable ...$messageables): array
    {
        $participants = $this->uniqueMessageables(array_values($messageables));

        if ($participants === []) {
            throw new InvalidArgumentException('At least one participant is required.');
        }

        $hash = ParticipantHash::make($participants);
        $created = ! isset($this->conversations[$hash]);

        if ($created) {
            $conversation = new Conversation;
            $conversation->forceFill([
                'id' => $this->nextConversationId++,
                'participant_hash' => $hash,
            ]);
            $conversation->syncOriginal();

            $this->conversations[$hash] = $conversation;
            $this->participantsByConversationKey[$hash] = [];

            foreach ($participants as $messageable) {
                $participant = new Participant;
                $participant->forceFill([
                    'id' => $this->nextParticipantId++,
                    'conversation_id' => $conversation->getKey(),
                    'messageable_type' => $messageable->getMorphClass(),
                    'messageable_id' => $messageable->getKey(),
                ]);
                $participant->syncOriginal();
                $participant->setRelation('conversation', $conversation);
                $this->participantsByConversationKey[$hash][] = $participant;
            }

            $this->fake->recordConversationCreated($conversation, $participants);

            $createdParticipantModels = new Collection($this->participantsByConversationKey[$hash]);
            event(new ConversationCreated($conversation, $createdParticipantModels));
        }

        return [$this->conversations[$hash], $created];
    }

    public function findConversation(Messageable ...$messageables): ?Conversation
    {
        $participants = $this->uniqueMessageables(array_values($messageables));

        if ($participants === []) {
            return null;
        }

        $hash = ParticipantHash::make($participants);

        return $this->conversations[$hash] ?? null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function sendMessage(Conversation $conversation, Messageable $sender, string $body, array $meta = []): Message
    {
        $this->assertParticipant($conversation, $sender);

        $sending = new MessageSending($conversation, $sender, $body, $meta);
        event($sending);

        if ($sending->isCancelled()) {
            throw new MessageRejectedException('Message sending was cancelled by a listener.');
        }

        $body = $sending->body;
        $meta = $sending->meta;

        $message = new Message;
        $message->forceFill([
            'id' => $this->nextMessageId++,
            'conversation_id' => $conversation->getKey(),
            'messageable_type' => $sender->getMorphClass(),
            'messageable_id' => $sender->getKey(),
            'body' => $body,
            'meta' => $meta === [] ? null : $meta,
            'sent_at' => now(),
        ]);
        $message->syncOriginal();
        $message->setRelation('conversation', $conversation);

        $this->messages[$message->getKey()] = $message;

        $this->fake->recordMessageSent($message, $conversation);
        event(new MessageSent($message, $conversation));

        return $message;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordEvent(Model $subject, Messageable $actor, string $event, array $meta = []): MessagingEvent
    {
        $conversation = $this->resolveConversationForSubject($subject);
        $participant = $this->findParticipantOrFail($conversation, $actor);
        $key = $this->messagingEventKey($subject->getMorphClass(), $subject->getKey(), $participant->getKey(), $event);

        if (isset($this->events[$key])) {
            return $this->events[$key];
        }

        $row = new MessagingEvent;
        $row->forceFill([
            'id' => $this->nextMessagingEventId++,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'participant_id' => $participant->getKey(),
            'event' => $event,
            'recorded_at' => now(),
            'meta' => $meta === [] ? null : $meta,
        ]);
        $row->syncOriginal();
        $this->events[$key] = $row;

        return $row;
    }

    public function paginateMessages(Conversation $conversation): CursorPaginator|LengthAwarePaginator
    {
        $items = collect($this->messages)
            ->filter(fn (Message $message): bool => (int) $message->conversation_id === (int) $conversation->getKey())
            ->sortBy('sent_at')
            ->values();

        return $this->paginateCollection($items);
    }

    public function emptyMessagesPagination(): CursorPaginator|LengthAwarePaginator
    {
        return $this->paginateCollection(collect());
    }

    /**
     * @param  Collection<int, Message>  $items
     */
    protected function paginateCollection(Collection $items): CursorPaginator|LengthAwarePaginator
    {
        $perPage = (int) config('messaging.pagination.per_page');
        $type = config('messaging.pagination.type');
        $path = '/';

        if ($type === 'offset') {
            return new LengthAwarePaginatorConcrete($items->values()->all(), $items->count(), $perPage, 1, [
                'path' => $path,
            ]);
        }

        return new CursorPaginatorConcrete($items->values()->all(), $perPage, null, [
            'path' => $path,
        ]);
    }

    public function editMessage(Message $message, Messageable $actor, string $newBody): Message
    {
        $this->assertMessageSender($message, $actor);

        $originalBody = $message->body;

        $message->forceFill([
            'body' => $newBody,
            'edited_at' => now(),
        ]);
        $message->syncOriginal();

        event(new MessageEdited($message, $originalBody));

        return $message;
    }

    public function deleteMessage(Message $message, Messageable $actor): void
    {
        $this->assertMessageSender($message, $actor);

        $conversation = $message->conversation;
        if (! $conversation instanceof Conversation) {
            throw new CannotMessageException('Message has no conversation.');
        }

        foreach ($this->events as $key => $event) {
            if (
                $event->subject_type === $message->getMorphClass()
                && (string) $event->subject_id === (string) $message->getKey()
            ) {
                unset($this->events[$key]);
            }
        }

        unset($this->messages[$message->getKey()]);

        event(new MessageDeleted($message, $conversation));
    }

    public function markReceived(Message $message, Messageable $actor): MessagingEvent
    {
        $conversation = $message->conversation;
        if (! $conversation instanceof Conversation) {
            throw new CannotMessageException('Message has no conversation.');
        }

        $participant = $this->findParticipantOrFail($conversation, $actor);
        $key = $this->messagingEventKey($message->getMorphClass(), $message->getKey(), $participant->getKey(), MessagingEventName::MessageReceived);

        if (isset($this->events[$key])) {
            return $this->events[$key];
        }

        $row = new MessagingEvent;
        $row->forceFill([
            'id' => $this->nextMessagingEventId++,
            'subject_type' => $message->getMorphClass(),
            'subject_id' => $message->getKey(),
            'participant_id' => $participant->getKey(),
            'event' => MessagingEventName::MessageReceived,
            'recorded_at' => now(),
            'meta' => null,
        ]);
        $row->syncOriginal();
        $this->events[$key] = $row;

        $this->fake->recordReceiptReceived($message->getKey(), $this->participantKey($actor));
        event(new MessageReceived($row, $message, $participant));

        return $row;
    }

    public function markRead(Message $message, Messageable $actor): MessagingEvent
    {
        $conversation = $message->conversation;
        if (! $conversation instanceof Conversation) {
            throw new CannotMessageException('Message has no conversation.');
        }

        $participant = $this->findParticipantOrFail($conversation, $actor);
        $key = $this->messagingEventKey($message->getMorphClass(), $message->getKey(), $participant->getKey(), MessagingEventName::MessageRead);

        if (isset($this->events[$key])) {
            return $this->events[$key];
        }

        $row = new MessagingEvent;
        $row->forceFill([
            'id' => $this->nextMessagingEventId++,
            'subject_type' => $message->getMorphClass(),
            'subject_id' => $message->getKey(),
            'participant_id' => $participant->getKey(),
            'event' => MessagingEventName::MessageRead,
            'recorded_at' => now(),
            'meta' => null,
        ]);
        $row->syncOriginal();
        $this->events[$key] = $row;

        $this->fake->recordReceiptRead($message->getKey(), $this->participantKey($actor));
        event(new MessageRead($row, $message, $participant));

        return $row;
    }

    public function findMessage(int|string $id): Message
    {
        if (! isset($this->messages[$id])) {
            throw new CannotMessageException("Message [{$id}] not found.");
        }

        $message = $this->messages[$id];

        $conversation = collect($this->conversations)->first(
            fn (Conversation $c): bool => (int) $c->getKey() === (int) $message->conversation_id
        );

        if ($conversation) {
            $message->setRelation('conversation', $conversation);
        }

        return $message;
    }

    public function findParticipantOrFail(Conversation $conversation, Messageable $messageable): Participant
    {
        $participant = $this->findParticipant($conversation, $messageable);

        if (! $participant) {
            throw new CannotMessageException('The given model is not a participant in this conversation.');
        }

        return $participant;
    }

    public function findParticipant(Conversation $conversation, Messageable $messageable): ?Participant
    {
        $hash = $conversation->participant_hash;

        foreach ($this->participantsByConversationKey[$hash] ?? [] as $participant) {
            if (
                $participant->messageable_type === $messageable->getMorphClass()
                && (string) $participant->messageable_id === (string) $messageable->getKey()
            ) {
                return $participant;
            }
        }

        return null;
    }

    public function unreadCount(Conversation $conversation, Messageable $messageable): int
    {
        $participant = $this->findParticipantOrFail($conversation, $messageable);

        $count = 0;

        foreach ($this->messages as $message) {
            if ((int) $message->conversation_id !== (int) $conversation->getKey()) {
                continue;
            }

            $readKey = $this->messagingEventKey($message->getMorphClass(), $message->getKey(), $participant->getKey(), MessagingEventName::MessageRead);

            if (! isset($this->events[$readKey])) {
                $count++;
            }
        }

        return $count;
    }

    public function markAllRead(Conversation $conversation, Messageable $reader): int
    {
        $participant = $this->findParticipantOrFail($conversation, $reader);

        $now = now();
        $affected = 0;

        foreach ($this->messages as $message) {
            if ((int) $message->conversation_id !== (int) $conversation->getKey()) {
                continue;
            }

            $readKey = $this->messagingEventKey($message->getMorphClass(), $message->getKey(), $participant->getKey(), MessagingEventName::MessageRead);

            if (isset($this->events[$readKey])) {
                continue;
            }

            $receivedKey = $this->messagingEventKey($message->getMorphClass(), $message->getKey(), $participant->getKey(), MessagingEventName::MessageReceived);

            if (! isset($this->events[$receivedKey])) {
                $received = new MessagingEvent;
                $received->forceFill([
                    'id' => $this->nextMessagingEventId++,
                    'subject_type' => $message->getMorphClass(),
                    'subject_id' => $message->getKey(),
                    'participant_id' => $participant->getKey(),
                    'event' => MessagingEventName::MessageReceived,
                    'recorded_at' => $now,
                    'meta' => null,
                ]);
                $received->syncOriginal();
                $this->events[$receivedKey] = $received;
            }

            $read = new MessagingEvent;
            $read->forceFill([
                'id' => $this->nextMessagingEventId++,
                'subject_type' => $message->getMorphClass(),
                'subject_id' => $message->getKey(),
                'participant_id' => $participant->getKey(),
                'event' => MessagingEventName::MessageRead,
                'recorded_at' => $now,
                'meta' => null,
            ]);
            $read->syncOriginal();
            $this->events[$readKey] = $read;
            $affected++;
        }

        if ($affected > 0) {
            event(new AllMessagesRead($conversation, $reader, $affected));
        }

        return $affected;
    }

    /**
     * @return Builder<Conversation>
     */
    public function conversationsFor(Messageable $messageable): Builder
    {
        /** @var class-string<Conversation> $conversationClass */
        $conversationClass = config('messaging.models.conversation');

        return $conversationClass::query()->whereRaw('0 = 1');
    }

    protected function messagingEventKey(string $subjectType, int|string $subjectId, int|string $participantId, string $event): string
    {
        return $subjectType.':'.(string) $subjectId.':'.(string) $participantId.':'.$event;
    }

    protected function participantKey(Messageable $messageable): string
    {
        return $messageable->getMorphClass().':'.$messageable->getKey();
    }
}
