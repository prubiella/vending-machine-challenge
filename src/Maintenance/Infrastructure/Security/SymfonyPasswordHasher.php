<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Security;

use App\Maintenance\Domain\Technician\HashedPassword;
use App\Maintenance\Domain\Technician\PasswordHasher;
use App\Maintenance\Domain\Technician\PlainPassword;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    private const string CACHE_KEY = 'maintenance.hash_of_no_technician';

    public function __construct(
        private PasswordHasherInterface $hasher,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function hash(PlainPassword $password): HashedPassword
    {
        return HashedPassword::fromString($this->hasher->hash($password->value()));
    }

    public function verify(HashedPassword $hash, #[\SensitiveParameter] string $password): bool
    {
        return $this->hasher->verify($hash->value(), $password);
    }

    public function verifyAgainstNoTechnician(#[\SensitiveParameter] string $password): void
    {
        $this->hasher->verify($this->hashOfNoTechnician(), $password);
    }

    /**
     * Hashed with the configured hasher, so verifying it costs what a real verification costs. It is
     * kept in the cache because PHP-FPM builds this class again on every request: hashing it there
     * would make an unknown username slower than a known one.
     */
    private function hashOfNoTechnician(): string
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        if (!$item->isHit()) {
            $item->set($this->hasher->hash('no technician has this password'));
            $this->cache->save($item);
        }

        $hash = $item->get();
        \assert(\is_string($hash));

        return $hash;
    }
}
