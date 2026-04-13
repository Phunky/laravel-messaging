<?php

namespace Phunky\LaravelMessaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Phunky\LaravelMessaging\Contracts\ConversationContract;

/**
 * @property int $id
 * @property string $participant_hash
 * @property array|null $meta
 */
class Conversation extends Model implements ConversationContract
{
    protected $guarded = [];

    public function getTable(): string
    {
        return messaging_table('conversations');
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(config('messaging.models.participant'), 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(config('messaging.models.message'), 'conversation_id')->orderBy('sent_at');
    }

    public function latestMessage(): HasOne
    {
        /** @var class-string<Message> $messageClass */
        $messageClass = config('messaging.models.message');

        return $this->hasOne($messageClass, 'conversation_id')->latestOfMany('sent_at');
    }

    public function events(): MorphMany
    {
        return $this->morphMany(config('messaging.models.event'), 'subject');
    }
}
