<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Persistence;

use App\Maintenance\Domain\Technician\Email;
use App\Maintenance\Domain\Technician\EmailTakenException;
use App\Maintenance\Domain\Technician\HashedPassword;
use App\Maintenance\Domain\Technician\Technician;
use App\Maintenance\Domain\Technician\TechnicianId;
use App\Maintenance\Domain\Technician\TechnicianRepository;
use App\Maintenance\Domain\Technician\Username;
use App\Maintenance\Domain\Technician\UsernameTakenException;
use App\Shared\Domain\DomainException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DbalTechnicianRepository implements TechnicianRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function add(Technician $technician): void
    {
        try {
            $this->connection->insert('technicians', [
                'id' => $technician->id()->value(),
                'username' => $technician->username()->value(),
                'email' => $technician->email()->value(),
                'password_hash' => $technician->password()->value(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->clashFor($technician);
        }
    }

    // Both the username and the email are unique: a stored username tells which one clashed.
    private function clashFor(Technician $technician): DomainException
    {
        if (null !== $this->byUsername($technician->username())) {
            return UsernameTakenException::alreadyTaken();
        }

        return EmailTakenException::alreadyTaken();
    }

    public function byUsername(Username $username): ?Technician
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, email, password_hash FROM technicians WHERE username = ?',
            [$username->value()],
        );

        if (false === $row) {
            return null;
        }

        return Technician::register(
            TechnicianId::fromString(self::column($row, 'id')),
            $username,
            Email::fromString(self::column($row, 'email')),
            HashedPassword::fromString(self::column($row, 'password_hash')),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function column(array $row, string $name): string
    {
        if (!\is_string($row[$name] ?? null)) {
            throw new \UnexpectedValueException(\sprintf('The technicians row has no %s.', $name));
        }

        return $row[$name];
    }
}
