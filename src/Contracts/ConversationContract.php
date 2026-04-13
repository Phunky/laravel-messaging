<?php

namespace Phunky\LaravelMessaging\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface ConversationContract
{
    public function participants(): HasMany;

    public function messages(): HasMany;

    public function latestMessage(): HasOne;

    public function events(): MorphMany;
}
