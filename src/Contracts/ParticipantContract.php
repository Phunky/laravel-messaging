<?php

namespace Phunky\LaravelMessaging\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface ParticipantContract
{
    public function conversation(): BelongsTo;

    public function messageable(): MorphTo;

    public function events(): HasMany;
}
