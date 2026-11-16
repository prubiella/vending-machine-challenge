<?php

declare(strict_types=1);

namespace App\Maintenance\Application\Command\CreateTechnician;

use App\Maintenance\Domain\Technician\Email;
use App\Maintenance\Domain\Technician\PasswordHasher;
use App\Maintenance\Domain\Technician\PlainPassword;
use App\Maintenance\Domain\Technician\Technician;
use App\Maintenance\Domain\Technician\TechnicianId;
use App\Maintenance\Domain\Technician\TechnicianRepository;
use App\Maintenance\Domain\Technician\Username;

final readonly class CreateTechnicianCommandHandler
{
    public function __construct(
        private TechnicianRepository $repository,
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function __invoke(CreateTechnicianCommand $command): void
    {
        $technician = Technician::register(
            TechnicianId::fromString($command->id),
            Username::fromString($command->username),
            Email::fromString($command->email),
            $this->passwordHasher->hash(PlainPassword::fromString($command->password)),
        );

        $this->repository->add($technician);
    }
}
