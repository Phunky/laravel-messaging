<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Phunky\LaravelMessaging\Facades\Messenger;
use Phunky\LaravelMessaging\Tests\Fixtures\TestMessagingExtension;
use Phunky\LaravelMessaging\Tests\Fixtures\User;

describe('extension system', function () {
    it('can listen to core events', function () {
        TestMessagingExtension::$eventHit = false;

        [$a, $b] = [
            User::create(['name' => 'A']),
            User::create(['name' => 'B']),
        ];

        Messenger::conversation($a, $b)->as($a)->send('e');

        expect(TestMessagingExtension::$eventHit)->toBeTrue();
    });

    it('loads migrations from an extension', function () {
        Artisan::call('migrate');

        expect(Schema::hasTable('extension_probe'))->toBeTrue();
    });

    it('can add macros to core models', function () {
        [$a, $b] = [
            User::create(['name' => 'A']),
            User::create(['name' => 'B']),
        ];

        $message = Messenger::conversation($a, $b)->as($a)->send('macro');

        expect($message->extensionProbe())->toBe('macro-ok');
    });
});
