<?php

namespace Phunky\LaravelMessaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Phunky\LaravelMessaging\Contracts\ParticipantContract;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string $messageable_type
 * @property int|string $messageable_id
 * @property array|null $meta
 */
class Participant extends Model implements ParticipantContract
{
    protected $guarded = [];

    public function getTable(): string
    {
        return messaging_table('participants');
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(config('messaging.models.conversation'), 'conversation_id');
    }

    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): HasMany
    {
        return $this->hasMany(config('messaging.models.event'), 'participant_id');
    }
}
