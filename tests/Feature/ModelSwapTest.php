<?php

use Phunky\LaravelMessaging\Facades\Messenger;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Tests\Fixtures\TestConversation;
use Phunky\LaravelMessaging\Tests\Fixtures\User;

describe('config model swapping', function () {
    beforeEach(function () {
        config(['messaging.models.conversation' => TestConversation::class]);
    });

    afterEach(function () {
        config(['messaging.models.conversation' => Conversation::class]);
    });

    it('uses the configured conversation class from the container', function () {
        [$a, $b] = [User::create(['name' => 'A']), User::create(['name' => 'B'])];

        Messenger::conversation($a, $b)->as($a)->send('swap probe');

        /** @var class-string<Conversation> $conversationClass */
        $conversationClass = config('messaging.models.conversation');
        $conversation = $conversationClass::query()->first();

        expect($conversation)->toBeInstanceOf(TestConversation::class)
            ->and(TestConversation::swappedModelProbe())->toBeTrue();
    });
});
