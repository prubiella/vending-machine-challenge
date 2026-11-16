<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance\Infrastructure\Persistence;

use App\Maintenance\Domain\Technician\Email;
use App\Maintenance\Domain\Technician\EmailTakenException;
use App\Maintenance\Domain\Technician\HashedPassword;
use App\Maintenance\Domain\Technician\Technician;
use App\Maintenance\Domain\Technician\TechnicianId;
use App\Maintenance\Domain\Technician\Username;
use App\Maintenance\Domain\Technician\UsernameTakenException;
use App\Maintenance\Infrastructure\Persistence\DbalTechnicianRepository;
use App\Tests\Integration\Maintenance\StartsWithNoTechnicians;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalTechnicianRepositoryTest extends KernelTestCase
{
    use StartsWithNoTechnicians;

    public function testGivesBackTheTechnicianItAddedByItsUsername(): void
    {
        $repository = self::repository();
        $technician = self::technician('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61', 'ana');

        $repository->add($technician);

        self::assertEquals($technician, $repository->byUsername(Username::fromString('ana')));
    }

    public function testGivesNothingForAnUnknownUsername(): void
    {
        self::repository()->add(self::technician('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61', 'ana'));

        self::assertNull(self::repository()->byUsername(Username::fromString('bob')));
    }

    public function testRefusesASecondTechnicianWithTheSameUsername(): void
    {
        $repository = self::repository();
        $repository->add(self::technician('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61', 'ana'));

        $this->expectException(UsernameTakenException::class);

        $repository->add(self::technician('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a62', 'ana', 'another@example.com'));
    }

    public function testRefusesASecondTechnicianWithTheSameEmail(): void
    {
        $repository = self::repository();
        $repository->add(self::technician('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a61', 'ana', 'shared@example.com'));

        $this->expectException(EmailTakenException::class);

        $repository->add(self::technician('0196f1d2-7c3a-7b9e-9a1d-3f5e8c2b4a62', 'bob', 'shared@example.com'));
    }

    private static function repository(): DbalTechnicianRepository
    {
        return self::getContainer()->get(DbalTechnicianRepository::class);
    }

    private static function technician(string $id, string $username, ?string $email = null): Technician
    {
        return Technician::register(
            TechnicianId::fromString($id),
            Username::fromString($username),
            Email::fromString($email ?? $username.'@example.com'),
            HashedPassword::fromString('$2y$04$hashOf.'.$username),
        );
    }
}
