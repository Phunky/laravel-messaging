<?php

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Phunky\LaravelMessaging\Contracts\ConversationContract;
use Phunky\LaravelMessaging\Contracts\MessageContract;
use Phunky\LaravelMessaging\Contracts\MessagingEventContract;
use Phunky\LaravelMessaging\Contracts\ParticipantContract;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Models\Participant;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('conversation implements conversation contract')
    ->expect(Conversation::class)
    ->toImplement(ConversationContract::class);

arch('message implements message contract')
    ->expect(Message::class)
    ->toImplement(MessageContract::class);

arch('messaging event implements messaging event contract')
    ->expect(MessagingEvent::class)
    ->toImplement(MessagingEventContract::class);

arch('participant implements participant contract')
    ->expect(Participant::class)
    ->toImplement(ParticipantContract::class);

arch('lifecycle events use Dispatchable')
    ->expect('Phunky\LaravelMessaging\Events')
    ->classes()
    ->toUseTrait(Dispatchable::class);

arch('lifecycle events use SerializesModels')
    ->expect('Phunky\LaravelMessaging\Events')
    ->classes()
    ->toUseTrait(SerializesModels::class);

arch('src must not reference the test namespace')
    ->expect('Phunky\LaravelMessaging')
    ->not->toUse('Phunky\LaravelMessaging\Tests');
