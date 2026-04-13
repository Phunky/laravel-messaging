<?php

namespace Phunky\LaravelMessaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Phunky\LaravelMessaging\Contracts\MessagingEventContract;

/**
 * @property int $id
 * @property string $subject_type
 * @property int|string $subject_id
 * @property int $participant_id
 * @property string $event
 * @property Carbon $recorded_at
 * @property array|null $meta
 */
class MessagingEvent extends Model implements MessagingEventContract
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return messaging_table('events');
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(config('messaging.models.participant'), 'participant_id');
    }
}
