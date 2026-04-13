<?php

namespace Phunky\LaravelMessaging\Testing;

use Illuminate\Database\Eloquent\Model;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Traits\HasMessaging;

/**
 * @internal Exists so PHPStan recognises {@see HasMessaging}; applications should use their own models.
 */
abstract class ExampleMessageable extends Model implements Messageable
{
    use HasMessaging;
}
