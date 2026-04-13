<?php

namespace Phunky\LaravelMessaging\Tests;

use Phunky\LaravelMessaging\Tests\Fixtures\TestMessagingExtension;

abstract class ExtensionTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('messaging.extensions', [
            TestMessagingExtension::class,
        ]);
    }
}
