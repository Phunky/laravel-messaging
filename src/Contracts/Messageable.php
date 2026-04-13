<?php

namespace Phunky\LaravelMessaging\Contracts;

use Illuminate\Database\Eloquent\Model;
use Phunky\LaravelMessaging\Traits\HasMessaging;

/**
 * Host application models that participate in messaging should use
 * {@see HasMessaging} and implement this contract.
 *
 * @phpstan-require-extends Model
 */
interface Messageable
{
    /**
     * @return string
     */
    public function getMorphClass();

    /**
     * @return int|string
     */
    public function getKey();
}
