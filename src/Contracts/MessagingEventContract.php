<?php

namespace Phunky\LaravelMessaging\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface MessagingEventContract
{
    public function subject(): MorphTo;

    public function participant(): BelongsTo;
}
