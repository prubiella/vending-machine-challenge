<?php

declare(strict_types=1);

namespace App\Maintenance\Application\Query\AuthenticateTechnician;

use App\Maintenance\Domain\Technician\InvalidCredentialsException;
use App\Maintenance\Domain\Technician\InvalidUsernameException;
use App\Maintenance\Domain\Technician\PasswordHasher;
use App\Maintenance\Domain\Technician\TechnicianRepository;
use App\Maintenance\Domain\Technician\Username;

final readonly class AuthenticateTechnicianQueryHandler
{
    public function __construct(
        private TechnicianRepository $repository,
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function __invoke(AuthenticateTechnicianQuery $query): AuthenticatedTechnicianResponse
    {
        try {
            $username = Username::fromString($query->username);
        } catch (InvalidUsernameException) {
            $this->passwordHasher->verifyAgainstNoTechnician($query->password);

            throw InvalidCredentialsException::wrongUsernameOrPassword();
        }

        $technician = $this->repository->byUsername($username);
        if (null === $technician) {
            $this->passwordHasher->verifyAgainstNoTechnician($query->password);

            throw InvalidCredentialsException::wrongUsernameOrPassword();
        }

        $technician->authenticate($query->password, $this->passwordHasher);

        return new AuthenticatedTechnicianResponse($technician->id()->value());
    }
}
