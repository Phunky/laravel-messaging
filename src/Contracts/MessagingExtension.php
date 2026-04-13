<?php

namespace Phunky\LaravelMessaging\Contracts;

use Illuminate\Contracts\Foundation\Application;

interface MessagingExtension
{
    public function register(Application $app): void;

    public function boot(Application $app): void;
}
