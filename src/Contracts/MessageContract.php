<?php

namespace Phunky\LaravelMessaging\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface MessageContract
{
    public function conversation(): BelongsTo;

    public function messageable(): MorphTo;

    public function events(): MorphMany;
}
