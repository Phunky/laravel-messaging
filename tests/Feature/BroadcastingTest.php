<?php

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Config;
use Phunky\LaravelMessaging\Events\ConversationCreated;
use Phunky\LaravelMessaging\Events\MessageSent;
use Phunky\LaravelMessaging\Facades\Messenger;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Tests\Fixtures\ExtensionBroadcastProbeEvent;
use Phunky\LaravelMessaging\Tests\Fixtures\User;

describe('broadcasting', function () {
    it('exposes broadcast channels when broadcasting is enabled', function () {
        Config::set('messaging.broadcasting.enabled', true);
        Config::set('messaging.broadcasting.channel_prefix', 'messaging');

        [$a, $b] = [User::create(['name' => 'A']), User::create(['name' => 'B'])];

        $message = Messenger::conversation($a, $b)->as($a)->send('broadcast probe');

        /** @var Conversation $conversation */
        $conversation = $message->conversation;

        $event = new MessageSent($message, $conversation);

        expect($event)->toBeInstanceOf(ShouldBroadcast::class)
            ->and($event->broadcastWhen())->toBeTrue();

        $channels = $event->broadcastOn();

        expect($channels[0]->name)->toBe('private-messaging.conversation.'.$conversation->getKey());
    });

    it('skips broadcasting when broadcasting is disabled', function () {
        Config::set('messaging.broadcasting.enabled', false);

        [$a, $b] = [User::create(['name' => 'A2']), User::create(['name' => 'B2'])];

        $message = Messenger::conversation($a, $b)->as($a)->send('no broadcast');

        $event = new MessageSent($message, $message->conversation);

        expect($event->broadcastWhen())->toBeFalse();
    });

    it('broadcasts ConversationCreated when broadcasting is enabled', function () {
        Config::set('messaging.broadcasting.enabled', true);
        Config::set('messaging.broadcasting.channel_prefix', 'messaging');

        [$a, $b] = [User::create(['name' => 'A3']), User::create(['name' => 'B3'])];

        $message = Messenger::conversation($a, $b)->as($a)->send('new convo');

        /** @var Conversation $conversation */
        $conversation = $message->conversation;

        $event = new ConversationCreated($conversation, $conversation->participants()->get());

        expect($event)->toBeInstanceOf(ShouldBroadcast::class)
            ->and($event->broadcastWhen())->toBeTrue();

        expect($event->broadcastOn()[0]->name)->toBe('private-messaging.conversation.'.$conversation->getKey());
    });

    it('broadcasts extension events that extend BroadcastableMessagingEvent', function () {
        Config::set('messaging.broadcasting.enabled', true);
        Config::set('messaging.broadcasting.channel_prefix', 'messaging');

        [$a, $b] = [User::create(['name' => 'A4']), User::create(['name' => 'B4'])];

        $message = Messenger::conversation($a, $b)->as($a)->send('extension probe');

        $conversationId = $message->conversation_id;

        $event = new ExtensionBroadcastProbeEvent($conversationId, 'extension-ok');

        expect($event)->toBeInstanceOf(ShouldBroadcast::class)
            ->and($event->broadcastWhen())->toBeTrue()
            ->and($event->broadcastOn()[0]->name)->toBe('private-messaging.conversation.'.$conversationId)
            ->and($event->probe)->toBe('extension-ok');
    });
});
