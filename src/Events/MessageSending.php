<?php

namespace Phunky\LaravelMessaging\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Models\Conversation;

class MessageSending
{
    use Dispatchable, SerializesModels;

    protected bool $cancelled = false;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public Conversation $conversation,
        public Messageable $sender,
        public string $body,
        public array $meta = [],
    ) {}

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
