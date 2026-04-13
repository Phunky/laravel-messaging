<?php

namespace Phunky\LaravelMessaging\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Phunky\LaravelMessaging\Contracts\Messageable;

/**
 * @phpstan-require-implements Messageable
 */
trait HasMessaging
{
    public function conversations(): MorphToMany
    {
        /** @var class-string<Model> $participantClass */
        $participantClass = config('messaging.models.participant');
        $participantTable = (new $participantClass)->getTable();

        return $this->morphToMany(
            config('messaging.models.conversation'),
            'messageable',
            $participantTable
        )->withTimestamps();
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(config('messaging.models.message'), 'messageable');
    }
}
