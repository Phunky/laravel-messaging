<?php

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Event;
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
use Phunky\LaravelMessaging\Facades\Messenger;
use Phunky\LaravelMessaging\MessagingEventName;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessaging\Tests\Fixtures\User;

function messagingUsers(): array
{
    return [
        User::create(['name' => 'Alice']),
        User::create(['name' => 'Bob']),
        User::create(['name' => 'Carol']),
    ];
}

describe('conversations', function () {
    it('creates a conversation between two users', function () {
        [$a, $b] = messagingUsers();

        [$conversation, $created] = app(MessagingService::class)->findOrCreateConversation($a, $b);

        expect($created)->toBeTrue()
            ->and($conversation->participants)->toHaveCount(2);
    });

    it('creates a conversation with more than two users', function () {
        [$a, $b, $c] = messagingUsers();

        [$conversation, $created] = app(MessagingService::class)->findOrCreateConversation($a, $b, $c);

        expect($created)->toBeTrue()
            ->and($conversation->participants)->toHaveCount(3);
    });

    it('creates a conversation with oneself', function () {
        [$a] = messagingUsers();

        [$conversation, $created] = app(MessagingService::class)->findOrCreateConversation($a);

        expect($created)->toBeTrue()
            ->and($conversation->participants)->toHaveCount(1);
    });

    it('returns existing conversation for same participant set', function () {
        [$a, $b] = messagingUsers();

        [$first, $c1] = app(MessagingService::class)->findOrCreateConversation($a, $b);
        [$second, $c2] = app(MessagingService::class)->findOrCreateConversation($a, $b);

        expect($c1)->toBeTrue()
            ->and($c2)->toBeFalse()
            ->and($second->is($first))->toBeTrue();
    });

    it('returns existing conversation regardless of participant order', function () {
        [$a, $b] = messagingUsers();

        [$first] = app(MessagingService::class)->findOrCreateConversation($a, $b);
        [$second] = app(MessagingService::class)->findOrCreateConversation($b, $a);

        expect($second->is($first))->toBeTrue();
    });

    it('cannot create a conversation with no participants', function () {
        expect(fn () => app(MessagingService::class)->findOrCreateConversation())
            ->toThrow(InvalidArgumentException::class);
    });

    it('cannot send a message as a non participant', function () {
        [$a, $b, $c] = messagingUsers();

        expect(
            fn () => Messenger::conversation($a, $b)->as($c)->send('hi')
        )->toThrow(CannotMessageException::class);
    });

    it('does not persist a conversation when only listing messages for a new pair', function () {
        [$a, $b] = messagingUsers();

        Messenger::conversation($a, $b)->messages();

        expect(Conversation::query()->count())->toBe(0);
    });
});

describe('messages', function () {
    it('can send a message to a conversation', function () {
        [$a, $b] = messagingUsers();

        $message = Messenger::conversation($a, $b)->as($a)->send('hello');

        expect($message->body)->toBe('hello')
            ->and($message->conversation)->not->toBeNull();
    });

    it('rolls back message creation when an atomic send callback fails', function () {
        [$a, $b] = messagingUsers();
        $service = app(MessagingService::class);
        [$conversation] = $service->findOrCreateConversation($a, $b);

        expect(fn () => $service->sendMessageUsing(
            $conversation,
            $a,
            'rollback',
            afterPersisted: fn () => throw new RuntimeException('after persisted failed'),
        ))->toThrow(RuntimeException::class);

        expect(Message::query()->where('body', 'rollback')->exists())->toBeFalse();
    });

    it('can send as any participant', function () {
        [$a, $b] = messagingUsers();

        $fromA = Messenger::conversation($a, $b)->as($a)->send('a');
        $fromB = Messenger::conversation($a, $b)->as($b)->send('b');

        expect($fromA->messageable_id)->toBe($a->id)
            ->and($fromB->messageable_id)->toBe($b->id);
    });

    it('can send a message to oneself', function () {
        [$a] = messagingUsers();

        $message = Messenger::conversation($a)->as($a)->send('solo');

        expect($message->body)->toBe('solo');
    });

    it('sets sent_at when sending', function () {
        [$a, $b] = messagingUsers();

        $this->freezeSecond();
        $message = Messenger::conversation($a, $b)->as($a)->send('t');

        expect($message->sent_at)->not->toBeNull()
            ->and($message->sent_at->timestamp)->toBe(now()->timestamp);
    });

    it('tracks conversation activity when messages change', function () {
        [$a, $b] = messagingUsers();

        $this->freezeSecond();
        $message = Messenger::conversation($a, $b)->as($a)->send('activity');
        $conversation = $message->conversation->refresh();

        expect($conversation->last_activity_at?->timestamp)->toBe(now()->timestamp);

        $this->travel(5)->seconds();
        Messenger::message($message->id)->as($a)->edit('activity updated');

        expect($conversation->refresh()->last_activity_at?->timestamp)->toBe(now()->timestamp);
    });

    it('returns messages ordered by sent_at', function () {
        [$a, $b] = messagingUsers();
        $service = app(MessagingService::class);

        [$conversation] = $service->findOrCreateConversation($a, $b);

        $this->freezeSecond();
        $m1 = $service->sendMessage($conversation, $a, 'first');
        $this->travel(5)->seconds();
        $service->sendMessage($conversation, $b, 'second');

        $ids = $conversation->messages()->pluck('id')->all();

        expect($ids[0])->toBe($m1->id);
    });

    it('paginates with cursor pagination by default', function () {
        config(['messaging.pagination.type' => 'cursor', 'messaging.pagination.per_page' => 2]);

        [$a, $b] = messagingUsers();

        for ($i = 0; $i < 3; $i++) {
            $this->travel(1)->seconds();
            Messenger::conversation($a, $b)->as($a)->send((string) $i);
        }

        $page = Messenger::conversation($a, $b)->messages();

        expect($page)->toBeInstanceOf(CursorPaginator::class)
            ->and($page->count())->toBe(2);
    });

    it('paginates with offset pagination when configured', function () {
        config(['messaging.pagination.type' => 'offset', 'messaging.pagination.per_page' => 2]);

        [$a, $b] = messagingUsers();

        for ($i = 0; $i < 5; $i++) {
            $this->travel(1)->seconds();
            Messenger::conversation($a, $b)->as($a)->send((string) $i);
        }

        $page = Messenger::conversation($a, $b)->messages();

        expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($page->perPage())->toBe(2)
            ->and($page->total())->toBe(5);
    });

    it('can edit a message as the sender', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('old');

        $updated = Messenger::message($message->id)->as($a)->edit('new');

        expect($updated->body)->toBe('new')
            ->and($updated->edited_at)->not->toBeNull();
    });

    it('cannot edit as another participant', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        expect(
            fn () => Messenger::message($message->id)->as($b)->edit('y')
        )->toThrow(CannotMessageException::class);
    });

    it('can delete as the sender', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('bye');

        Messenger::message($message->id)->as($a)->delete();

        expect(Message::query()->withTrashed()->find($message->id)?->trashed())->toBeTrue();
    });

    it('cannot delete as another participant', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        expect(
            fn () => Messenger::message($message->id)->as($b)->delete()
        )->toThrow(CannotMessageException::class);
    });

    it('soft deletes a message', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        Messenger::message($message->id)->as($a)->delete();

        expect(Message::query()->find($message->id))->toBeNull();
    });

    it('excludes soft deleted messages from conversation messages', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('gone');
        $conversation = $message->conversation;

        Messenger::message($message->id)->as($a)->delete();

        expect($conversation->messages()->count())->toBe(0);
    });
});

describe('messaging events', function () {
    it('does not persist messaging events when a message is sent', function () {
        [$a, $b, $c] = messagingUsers();
        Messenger::conversation($a, $b, $c)->as($a)->send('all');

        expect(MessagingEvent::query()->count())->toBe(0);
    });

    it('can record message received', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('r');

        Messenger::message($message->id)->as($b)->received();

        $event = MessagingEvent::query()
            ->where('subject_id', $message->id)
            ->where('event', MessagingEventName::MessageReceived)
            ->whereHas('participant', fn ($q) => $q
                ->where('messageable_type', $b->getMorphClass())
                ->where('messageable_id', $b->id))
            ->first();

        expect($event?->recorded_at)->not->toBeNull();
    });

    it('cannot mark received for a non participant', function () {
        [$a, $b, $c] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        expect(
            fn () => Messenger::message($message->id)->as($c)->received()
        )->toThrow(CannotMessageException::class);
    });

    it('can record message read', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('r');

        Messenger::message($message->id)->as($b)->read();

        $event = MessagingEvent::query()
            ->where('subject_id', $message->id)
            ->where('event', MessagingEventName::MessageRead)
            ->whereHas('participant', fn ($q) => $q
                ->where('messageable_type', $b->getMorphClass())
                ->where('messageable_id', $b->id))
            ->first();

        expect($event?->recorded_at)->not->toBeNull();
    });

    it('cannot mark read for a non participant', function () {
        [$a, $b, $c] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        expect(
            fn () => Messenger::message($message->id)->as($c)->read()
        )->toThrow(CannotMessageException::class);
    });

    it('does not change recorded_at for message.received if already recorded', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        Messenger::message($message->id)->as($b)->received();

        $event = MessagingEvent::query()
            ->where('subject_id', $message->id)
            ->where('event', MessagingEventName::MessageReceived)
            ->whereHas('participant', fn ($q) => $q
                ->where('messageable_type', $b->getMorphClass())
                ->where('messageable_id', $b->id))
            ->first();

        $first = $event->recorded_at->clone();

        $this->travel(5)->minutes();

        Messenger::message($message->id)->as($b)->received();

        $event->refresh();

        expect($event->recorded_at->equalTo($first))->toBeTrue();
    });

    it('does not change recorded_at for message.read if already recorded', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        Messenger::message($message->id)->as($b)->read();

        $event = MessagingEvent::query()
            ->where('subject_id', $message->id)
            ->where('event', MessagingEventName::MessageRead)
            ->whereHas('participant', fn ($q) => $q
                ->where('messageable_type', $b->getMorphClass())
                ->where('messageable_id', $b->id))
            ->first();

        $first = $event->recorded_at->clone();

        $this->travel(5)->minutes();

        Messenger::message($message->id)->as($b)->read();

        $event->refresh();

        expect($event->recorded_at->equalTo($first))->toBeTrue();
    });

    it('records a conversation invite accepted event', function () {
        [$a, $b] = messagingUsers();
        Messenger::conversation($a, $b)->as($a)->send('hi');

        $event = Messenger::conversation($a, $b)->as($b)->recordInviteAccepted(['token' => 'abc']);

        expect($event->event)->toBe(MessagingEventName::ConversationInviteAccepted)
            ->and($event->meta)->toBe(['token' => 'abc']);
    });

    it('records a conversation member left event', function () {
        [$a, $b] = messagingUsers();
        Messenger::conversation($a, $b)->as($a)->send('bye');

        $event = Messenger::conversation($a, $b)->as($b)->recordMemberLeft();

        expect($event->event)->toBe(MessagingEventName::ConversationMemberLeft);
    });

    it('can record a custom message-scoped event with meta', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        $event = Messenger::message($message->id)->as($b)->recordEvent('message.voice.first_play', ['ms' => 1200]);

        expect($event->event)->toBe('message.voice.first_play')
            ->and($event->meta)->toBe(['ms' => 1200]);
    });
});

describe('events', function () {
    it('fires ConversationCreated when a new conversation is created', function () {
        [$a, $b] = messagingUsers();
        Event::fake([ConversationCreated::class]);

        app(MessagingService::class)->findOrCreateConversation($a, $b);

        Event::assertDispatched(ConversationCreated::class);
    });

    it('does not fire ConversationCreated when conversation already exists', function () {
        [$a, $b] = messagingUsers();
        $service = app(MessagingService::class);

        $service->findOrCreateConversation($a, $b);

        Event::fake([ConversationCreated::class]);

        $service->findOrCreateConversation($b, $a);

        Event::assertNotDispatched(ConversationCreated::class);
    });

    it('fires MessageSent when a message is sent', function () {
        [$a, $b] = messagingUsers();
        Event::fake([MessageSent::class]);

        Messenger::conversation($a, $b)->as($a)->send('e');

        Event::assertDispatched(MessageSent::class);
    });

    it('fires MessageEdited with original body', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('orig');
        Event::fake([MessageEdited::class]);

        Messenger::message($message->id)->as($a)->edit('updated');

        Event::assertDispatched(MessageEdited::class, fn (MessageEdited $e): bool => $e->originalBody === 'orig');
    });

    it('fires MessageDeleted when message is deleted', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('d');
        Event::fake([MessageDeleted::class]);

        Messenger::message($message->id)->as($a)->delete();

        Event::assertDispatched(MessageDeleted::class);
    });

    it('fires MessageReceived when message marked received', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('r');
        Event::fake([MessageReceived::class]);

        Messenger::message($message->id)->as($b)->received();

        Event::assertDispatched(MessageReceived::class);
    });

    it('does not fire MessageReceived again when already received', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('r');
        Event::fake([MessageReceived::class]);

        Messenger::message($message->id)->as($b)->received();

        Event::assertDispatchedTimes(MessageReceived::class, 1);

        Messenger::message($message->id)->as($b)->received();

        Event::assertDispatchedTimes(MessageReceived::class, 1);
    });

    it('fires MessageRead when message marked read', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('r');
        Event::fake([MessageRead::class]);

        Messenger::message($message->id)->as($b)->read();

        Event::assertDispatched(MessageRead::class);
    });

    it('does not fire MessageRead again when already read', function () {
        [$a, $b] = messagingUsers();
        $message = Messenger::conversation($a, $b)->as($a)->send('r');
        Event::fake([MessageRead::class]);

        Messenger::message($message->id)->as($b)->read();

        Event::assertDispatchedTimes(MessageRead::class, 1);

        Messenger::message($message->id)->as($b)->read();

        Event::assertDispatchedTimes(MessageRead::class, 1);
    });
});

describe('message sending lifecycle', function () {
    it('throws when message sending is cancelled by a listener', function () {
        Event::listen(MessageSending::class, fn (MessageSending $e) => $e->cancel());

        try {
            [$a, $b] = messagingUsers();

            expect(fn () => Messenger::conversation($a, $b)->as($a)->send('blocked'))
                ->toThrow(MessageRejectedException::class);
        } finally {
            app('events')->forget(MessageSending::class);
        }
    });
});

describe('message meta on send', function () {
    it('persists meta passed to send()', function () {
        [$a, $b] = messagingUsers();

        $message = Messenger::conversation($a, $b)->as($a)->send('with meta', meta: ['source' => 'test']);

        $message->refresh();

        expect($message->meta)->toBe(['source' => 'test']);
    });
});

describe('has messaging trait', function () {
    it('exposes conversation and message relations for the messageable', function () {
        [$a, $b] = messagingUsers();

        Messenger::conversation($a, $b)->as($a)->send('trait probe');

        expect($a->conversations()->count())->toBe(1)
            ->and($a->messages()->count())->toBe(1);
    });
});

describe('service lookups', function () {
    it('returns null from findConversation when no conversation exists', function () {
        [$a, $b] = messagingUsers();

        expect(app(MessagingService::class)->findConversation($a, $b))->toBeNull();
    });

    it('throws when findMessage cannot resolve an id', function () {
        expect(fn () => app(MessagingService::class)->findMessage(999_999_999))
            ->toThrow(CannotMessageException::class);
    });
});

describe('inbox and read state', function () {
    it('reports unread count for the acting participant', function () {
        [$a, $b] = messagingUsers();

        Messenger::conversation($a, $b)->as($a)->send('hello');

        expect(Messenger::conversation($a, $b)->as($b)->unreadCount())->toBe(1);

        Messenger::conversation($a, $b)->as($b)->markAllRead();

        expect(Messenger::conversation($a, $b)->as($b)->unreadCount())->toBe(0);
    });

    it('dispatches AllMessagesRead when markAllRead records read events', function () {
        [$a, $b] = messagingUsers();

        Messenger::conversation($a, $b)->as($a)->send('one');
        Messenger::conversation($a, $b)->as($a)->send('two');

        Event::fake([AllMessagesRead::class]);

        $count = Messenger::conversation($a, $b)->as($b)->markAllRead();

        expect($count)->toBe(2);

        Event::assertDispatched(AllMessagesRead::class);
    });

    it('lists conversations for a messageable ordered by latest message', function () {
        [$a, $b, $c] = messagingUsers();

        Messenger::conversation($a, $b)->as($a)->send('older');
        $this->travel(5)->seconds();
        Messenger::conversation($a, $c)->as($a)->send('newer');

        $conversations = Messenger::conversationsFor($a)->get();

        expect($conversations)->toHaveCount(2);

        expect($conversations->first()->messages_max_sent_at >= $conversations->last()->messages_max_sent_at)->toBeTrue();
    });
});

describe('meta json', function () {
    it('round-trips meta on conversation, message, and messaging event', function () {
        [$a, $b] = messagingUsers();

        $message = Messenger::conversation($a, $b)->as($a)->send('meta probe');
        /** @var Conversation|null $conversation */
        $conversation = Conversation::query()->find($message->conversation_id);
        expect($conversation)->not->toBeNull();

        $conversation->forceFill(['meta' => ['scope' => 'conversation']])->save();
        $message->forceFill(['meta' => ['scope' => 'message']])->save();

        $messagingEvent = app(MessagingService::class)->recordEvent($message, $b, 'meta.probe', ['scope' => 'event']);

        $conversation->refresh();
        $message->refresh();
        $messagingEvent->refresh();

        expect($conversation->meta)->toBe(['scope' => 'conversation'])
            ->and($message->meta)->toBe(['scope' => 'message'])
            ->and($messagingEvent->meta)->toBe(['scope' => 'event']);
    });
});

describe('fake messenger', function () {
    it('records sent messages', function () {
        [$a, $b] = messagingUsers();
        $fake = Messenger::fake();

        Messenger::conversation($a, $b)->as($a)->send('f');

        $fake->assertMessageSent(fn (Message $m): bool => $m->body === 'f');
    });

    it('asserts when no message sent', function () {
        [$a, $b] = messagingUsers();
        $fake = Messenger::fake();

        $fake->assertMessageNotSent();
    });

    it('asserts when no message matches callback', function () {
        [$a, $b] = messagingUsers();
        $fake = Messenger::fake();

        Messenger::conversation($a, $b)->as($a)->send('a');

        $fake->assertMessageNotSent(fn (Message $m): bool => $m->body === 'b');
    });

    it('records created conversations', function () {
        [$a, $b] = messagingUsers();
        $fake = Messenger::fake();

        Messenger::conversation($a, $b)->as($a)->send('x');

        $fake->assertConversationCreated();
    });

    it('does not record conversation for messages-only fetch', function () {
        [$a, $b] = messagingUsers();
        $fake = Messenger::fake();

        Messenger::conversation($a, $b)->messages();

        $fake->assertConversationNotCreated();
    });

    it('records received and read interactions', function () {
        [$a, $b] = messagingUsers();
        $fake = Messenger::fake();
        $message = Messenger::conversation($a, $b)->as($a)->send('x');

        Messenger::message($message->id)->as($b)->received();
        Messenger::message($message->id)->as($b)->read();

        $key = $b->getMorphClass().':'.$b->getKey();

        $fake->assertReceiptMarkedReceived($message->id, $key);
        $fake->assertReceiptMarkedRead($message->id, $key);
    });
});
