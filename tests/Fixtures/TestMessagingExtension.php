<?php

namespace Phunky\LaravelMessaging\Tests\Fixtures;

use Illuminate\Contracts\Foundation\Application;
use Phunky\LaravelMessaging\Contracts\MessagingExtension;
use Phunky\LaravelMessaging\Events\MessageSent;
use Phunky\LaravelMessaging\Models\Message;

class TestMessagingExtension implements MessagingExtension
{
    public static bool $eventHit = false;

    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        $app['events']->listen(MessageSent::class, function () {
            self::$eventHit = true;
        });

        $migrations = realpath(__DIR__.'/../database/extension_migrations');
        if ($migrations !== false) {
            $app->make('migrator')->path($migrations);
        }

        Message::macro('extensionProbe', function () {
            return 'macro-ok';
        });
    }
}
