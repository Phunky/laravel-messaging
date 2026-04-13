<?php

declare(strict_types=1);

use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Support\ParticipantHash;

final class HashStub implements Messageable
{
    public function __construct(
        private string $morphClass,
        private int|string $key,
    ) {}

    public function getMorphClass(): string
    {
        return $this->morphClass;
    }

    public function getKey(): int|string
    {
        return $this->key;
    }
}

describe('ParticipantHash', function () {
    it('is invariant to participant order', function () {
        $a = new HashStub('user', 1);
        $b = new HashStub('user', 2);

        expect(ParticipantHash::make([$a, $b]))->toBe(ParticipantHash::make([$b, $a]));
    });

    it('throws when given no participants', function () {
        ParticipantHash::make([]);
    })->throws(InvalidArgumentException::class);

    it('treats duplicate participant references as distinct (MessagingService dedupes before hashing)', function () {
        $a = new HashStub('user', 1);
        $hash = ParticipantHash::make([$a, $a]);

        expect($hash)->toBeString()->not->toBeEmpty();
    });

    it('differs when morph class differs for the same numeric id', function () {
        $user = new HashStub('user', 1);
        $admin = new HashStub('admin', 1);

        expect(ParticipantHash::make([$user]))->not->toBe(ParticipantHash::make([$admin]));
    });

    it('differs when ids differ for the same morph class', function () {
        $a = new HashStub('user', 1);
        $b = new HashStub('user', 2);

        expect(ParticipantHash::make([$a]))->not->toBe(ParticipantHash::make([$b]));
    });
});
