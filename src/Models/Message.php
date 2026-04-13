<?php

namespace Phunky\LaravelMessaging\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Phunky\LaravelMessaging\Contracts\MessageContract;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string $messageable_type
 * @property int|string $messageable_id
 * @property string $body
 * @property array|null $meta
 * @property Carbon $sent_at
 * @property Carbon|null $edited_at
 */
class Message extends Model implements MessageContract
{
    use SoftDeletes;

    /**
     * @var array<string, object|callable>
     */
    protected static array $messagingMacros = [];

    protected $guarded = [];

    public function getTable(): string
    {
        return messaging_table('messages');
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'sent_at' => 'datetime',
            'edited_at' => 'datetime',
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

    public function events(): MorphMany
    {
        return $this->morphMany(config('messaging.models.event'), 'subject');
    }

    /**
     * Register a dynamic instance macro (extension contract).
     *
     * @param  (Closure(static): mixed)|callable  $macro
     */
    public static function macro(string $name, object|callable $macro): void
    {
        static::$messagingMacros[$name] = $macro;
    }

    public static function hasMacro(string $name): bool
    {
        return isset(static::$messagingMacros[$name]);
    }

    public static function flushMacros(): void
    {
        static::$messagingMacros = [];
    }

    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            $macro = static::$messagingMacros[$method];

            if ($macro instanceof Closure) {
                $macro = $macro->bindTo($this, static::class);
            }

            return $macro(...$parameters);
        }

        return parent::__call($method, $parameters);
    }
}
