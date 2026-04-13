<?php

namespace Phunky\LaravelMessaging\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator as CursorPaginatorConcrete;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
use Phunky\LaravelMessaging\Support\ParticipantHash;

class MessagingService
{
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
        /** @var class-string<Conversation> $conversationClass */
        $conversationClass = config('messaging.models.conversation');
        /** @var class-string<Participant> $participantClass */
        $participantClass = config('messaging.models.participant');

        return DB::transaction(function () use ($hash, $participants, $conversationClass, $participantClass): array {
            /** @var Conversation|null $conversation */
            $conversation = $conversationClass::query()
                ->where('participant_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($conversation) {
                return [$conversation, false];
            }

            /** @var Conversation $conversation */
            $conversation = $conversationClass::query()->create([
                'participant_hash' => $hash,
            ]);

            $createdParticipants = new Collection;

            foreach ($participants as $messageable) {
                /** @var Participant $participant */
                $participant = $participantClass::query()->create([
                    'conversation_id' => $conversation->getKey(),
                    'messageable_type' => $messageable->getMorphClass(),
                    'messageable_id' => $messageable->getKey(),
                ]);
                $createdParticipants->push($participant);
            }

            event(new ConversationCreated($conversation, $createdParticipants));

            return [$conversation, true];
        });
    }

    public function findConversation(Messageable ...$messageables): ?Conversation
    {
        $participants = $this->uniqueMessageables(array_values($messageables));

        if ($participants === []) {
            return null;
        }

        $hash = ParticipantHash::make($participants);
        /** @var class-string<Conversation> $conversationClass */
        $conversationClass = config('messaging.models.conversation');

        return $conversationClass::query()
            ->where('participant_hash', $hash)
            ->first();
    }

    /**
     * Send a message. Empty `$meta` arrays are stored as `null` in the database.
     *
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

        /** @var class-string<Message> $messageClass */
        $messageClass = config('messaging.models.message');

        return DB::transaction(function () use ($conversation, $sender, $body, $meta, $messageClass): Message {
            /** @var Message $message */
            $message = $messageClass::query()->create([
                'conversation_id' => $conversation->getKey(),
                'messageable_type' => $sender->getMorphClass(),
                'messageable_id' => $sender->getKey(),
                'body' => $body,
                'meta' => $meta === [] ? null : $meta,
                'sent_at' => now(),
            ]);

            event(new MessageSent($message, $conversation));

            return $message;
        });
    }

    /**
     * Record an event for a message or conversation subject (idempotent for a given event name).
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordEvent(Model $subject, Messageable $actor, string $event, array $meta = []): MessagingEvent
    {
        $conversation = $this->resolveConversationForSubject($subject);

        $participant = $this->findParticipantOrFail($conversation, $actor);

        return $this->firstOrCreateMessagingEvent($subject, $participant, $event, $meta);
    }

    public function paginateMessages(Conversation $conversation): CursorPaginator|LengthAwarePaginator
    {
        $messages = $conversation->messages();

        $perPage = (int) config('messaging.pagination.per_page');
        $type = config('messaging.pagination.type');

        if ($type === 'offset') {
            return $messages->paginate($perPage);
        }

        return $messages->cursorPaginate($perPage);
    }

    public function emptyMessagesPagination(): CursorPaginator|LengthAwarePaginator
    {
        return $this->emptyPaginator();
    }

    public function editMessage(Message $message, Messageable $actor, string $newBody): Message
    {
        $this->assertMessageSender($message, $actor);

        $originalBody = $message->body;

        $message->update([
            'body' => $newBody,
            'edited_at' => now(),
        ]);

        event(new MessageEdited($message, $originalBody));

        $message->refresh();

        return $message;
    }

    public function deleteMessage(Message $message, Messageable $actor): void
    {
        $this->assertMessageSender($message, $actor);

        $conversation = $message->conversation;
        if (! $conversation instanceof Conversation) {
            throw new CannotMessageException('Message has no conversation.');
        }

        $message->delete();

        event(new MessageDeleted($message, $conversation));
    }

    public function markReceived(Message $message, Messageable $actor): MessagingEvent
    {
        $conversation = $message->conversation;
        if (! $conversation instanceof Conversation) {
            throw new CannotMessageException('Message has no conversation.');
        }

        $participant = $this->findParticipantOrFail($conversation, $actor);

        /** @var class-string<MessagingEvent> $eventClass */
        $eventClass = config('messaging.models.event');

        /** @var MessagingEvent $row */
        $row = $eventClass::query()->firstOrCreate(
            [
                'subject_type' => $message->getMorphClass(),
                'subject_id' => $message->getKey(),
                'participant_id' => $participant->getKey(),
                'event' => MessagingEventName::MessageReceived,
            ],
            [
                'recorded_at' => now(),
                'meta' => null,
            ]
        );

        if ($row->wasRecentlyCreated) {
            event(new MessageReceived($row, $message, $participant));
        }

        return $row;
    }

    public function markRead(Message $message, Messageable $actor): MessagingEvent
    {
        $conversation = $message->conversation;
        if (! $conversation instanceof Conversation) {
            throw new CannotMessageException('Message has no conversation.');
        }

        $participant = $this->findParticipantOrFail($conversation, $actor);

        /** @var class-string<MessagingEvent> $eventClass */
        $eventClass = config('messaging.models.event');

        /** @var MessagingEvent $row */
        $row = $eventClass::query()->firstOrCreate(
            [
                'subject_type' => $message->getMorphClass(),
                'subject_id' => $message->getKey(),
                'participant_id' => $participant->getKey(),
                'event' => MessagingEventName::MessageRead,
            ],
            [
                'recorded_at' => now(),
                'meta' => null,
            ]
        );

        if ($row->wasRecentlyCreated) {
            event(new MessageRead($row, $message, $participant));
        }

        return $row;
    }

    public function unreadCount(Conversation $conversation, Messageable $messageable): int
    {
        $participant = $this->findParticipantOrFail($conversation, $messageable);
        /** @var class-string<Message> $messageClass */
        $messageClass = config('messaging.models.message');

        return (int) $messageClass::query()
            ->where('conversation_id', $conversation->getKey())
            ->whereDoesntHave('events', function (Builder $q) use ($participant): void {
                $q->where('participant_id', $participant->getKey())
                    ->where('event', MessagingEventName::MessageRead);
            })
            ->count();
    }

    public function markAllRead(Conversation $conversation, Messageable $reader): int
    {
        $participant = $this->findParticipantOrFail($conversation, $reader);
        /** @var class-string<Message> $messageClass */
        $messageClass = config('messaging.models.message');
        /** @var class-string<MessagingEvent> $eventClass */
        $eventClass = config('messaging.models.event');

        $now = now();

        // Collect unread message IDs
        $messageIds = $messageClass::query()
            ->where('conversation_id', $conversation->getKey())
            ->whereDoesntHave('events', function (Builder $q) use ($participant): void {
                $q->where('participant_id', $participant->getKey())
                    ->where('event', MessagingEventName::MessageRead);
            })
            ->pluck('id')
            ->all();

        if ($messageIds === []) {
            return 0;
        }

        $messageMorphClass = (new $messageClass)->getMorphClass();
        $affectedCount = count($messageIds);

        // Bulk insert received events (idempotent via unique constraint)
        $receivedRows = array_map(fn ($id) => [
            'subject_type' => $messageMorphClass,
            'subject_id' => $id,
            'participant_id' => $participant->getKey(),
            'event' => MessagingEventName::MessageReceived,
            'recorded_at' => $now,
            'meta' => null,
        ], $messageIds);

        $eventClass::query()->insertOrIgnore($receivedRows);

        // Bulk insert read events (idempotent via unique constraint)
        $readRows = array_map(fn ($id) => [
            'subject_type' => $messageMorphClass,
            'subject_id' => $id,
            'participant_id' => $participant->getKey(),
            'event' => MessagingEventName::MessageRead,
            'recorded_at' => $now,
            'meta' => null,
        ], $messageIds);

        $eventClass::query()->insertOrIgnore($readRows);

        if ($affectedCount > 0) {
            event(new AllMessagesRead($conversation, $reader, $affectedCount));
        }

        return $affectedCount;
    }

    /**
     * Conversations the messageable participates in, ordered by latest message time (desc).
     * Adds `unread_count` and `messages_max_sent_at` attributes; eager-loads `latestMessage`.
     *
     * @return Builder<Conversation>
     */
    public function conversationsFor(Messageable $messageable): Builder
    {
        /** @var class-string<Conversation> $conversationClass */
        $conversationClass = config('messaging.models.conversation');
        /** @var class-string<Message> $messageClass */
        $messageClass = config('messaging.models.message');
        /** @var class-string<MessagingEvent> $eventModelClass */
        $eventModelClass = config('messaging.models.event');
        /** @var class-string<Participant> $participantClass */
        $participantClass = config('messaging.models.participant');

        $conversationTable = (new $conversationClass)->getTable();
        $messageTable = (new $messageClass)->getTable();
        $eventTable = (new $eventModelClass)->getTable();
        $participantTable = (new $participantClass)->getTable();

        $messageMorph = (new $messageClass)->getMorphClass();

        return $conversationClass::query()
            ->whereHas('participants', function (Builder $q) use ($messageable): void {
                $q->where('messageable_type', $messageable->getMorphClass())
                    ->where('messageable_id', $messageable->getKey());
            })
            ->with(['latestMessage'])
            ->withMax('messages', 'sent_at')
            ->orderByDesc('messages_max_sent_at')
            ->selectSub(
                DB::table($messageTable.' as m')
                    ->whereColumn('m.conversation_id', $conversationTable.'.id')
                    ->whereNull('m.deleted_at')
                    ->whereNotExists(function ($query) use ($eventTable, $messageMorph, $messageable, $participantTable, $conversationTable): void {
                        $query->select(DB::raw(1))
                            ->from($eventTable.' as e')
                            ->whereColumn('e.subject_id', 'm.id')
                            ->where('e.subject_type', $messageMorph)
                            ->where('e.event', MessagingEventName::MessageRead)
                            ->whereRaw(
                                'e.participant_id = (select p.id from '.$participantTable.' as p where p.conversation_id = '.$conversationTable.'.id and p.messageable_type = ? and p.messageable_id = ? limit 1)',
                                [$messageable->getMorphClass(), $messageable->getKey()]
                            );
                    })
                    ->selectRaw('count(*)'),
                'unread_count'
            );
    }

    public function findMessage(int|string $id): Message
    {
        /** @var class-string<Message> $messageClass */
        $messageClass = config('messaging.models.message');

        /** @var Message|null $message */
        $message = $messageClass::query()->find($id);

        if (! $message) {
            throw new CannotMessageException("Message [{$id}] not found.");
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
        /** @var class-string<Participant> $participantClass */
        $participantClass = config('messaging.models.participant');

        return $participantClass::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('messageable_type', $messageable->getMorphClass())
            ->where('messageable_id', $messageable->getKey())
            ->first();
    }

    public function assertParticipant(Conversation $conversation, Messageable $messageable): void
    {
        $this->findParticipantOrFail($conversation, $messageable);
    }

    public function assertMessageSender(Message $message, Messageable $messageable): void
    {
        if (
            $message->messageable_type !== $messageable->getMorphClass()
            || (string) $message->messageable_id !== (string) $messageable->getKey()
        ) {
            throw new CannotMessageException('Only the sender can perform this action on the message.');
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function firstOrCreateMessagingEvent(Model $subject, Participant $participant, string $event, array $meta): MessagingEvent
    {
        /** @var class-string<MessagingEvent> $eventClass */
        $eventClass = config('messaging.models.event');

        /** @var MessagingEvent $row */
        $row = $eventClass::query()->firstOrCreate(
            [
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'participant_id' => $participant->getKey(),
                'event' => $event,
            ],
            [
                'recorded_at' => now(),
                'meta' => $meta === [] ? null : $meta,
            ]
        );

        return $row;
    }

    protected function resolveConversationForSubject(Model $subject): Conversation
    {
        if ($subject instanceof Message) {
            $conversation = $subject->conversation;
            if (! $conversation instanceof Conversation) {
                throw new CannotMessageException('Message has no conversation.');
            }

            return $conversation;
        }

        if ($subject instanceof Conversation) {
            return $subject;
        }

        throw new InvalidArgumentException('Subject must be a '.Message::class.' or '.Conversation::class.'.');
    }

    /**
     * @param  array<int, Messageable>  $messageables
     * @return array<int, Messageable>
     */
    protected function uniqueMessageables(array $messageables): array
    {
        return Collection::make($messageables)
            ->unique(fn (Messageable $messageable): string => $messageable->getMorphClass().':'.(string) $messageable->getKey())
            ->values()
            ->all();
    }

    protected function emptyPaginator(): CursorPaginator|LengthAwarePaginator
    {
        $perPage = (int) config('messaging.pagination.per_page');
        $type = config('messaging.pagination.type');
        $path = '/';

        if ($type === 'offset') {
            return new LengthAwarePaginatorConcrete([], 0, $perPage, 1, [
                'path' => $path,
            ]);
        }

        return new CursorPaginatorConcrete([], $perPage, null, [
            'path' => $path,
        ]);
    }
}
