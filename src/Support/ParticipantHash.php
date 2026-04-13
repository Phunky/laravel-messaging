<?php

namespace Phunky\LaravelMessaging\Support;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Phunky\LaravelMessaging\Contracts\Messageable;

class ParticipantHash
{
    /**
     * @param  array<int, Messageable>  $messageables
     */
    public static function make(array $messageables): string
    {
        if ($messageables === []) {
            throw new InvalidArgumentException('At least one participant is required to compute a participant hash.');
        }

        $pairs = Collection::make($messageables)
            ->map(fn (Messageable $messageable) => [
                'type' => $messageable->getMorphClass(),
                'id' => (string) $messageable->getKey(),
            ])
            ->sort(fn ($a, $b) => [$a['type'], $a['id']] <=> [$b['type'], $b['id']])
            ->values()
            ->all();

        return hash('sha256', json_encode($pairs, JSON_THROW_ON_ERROR));
    }
}
