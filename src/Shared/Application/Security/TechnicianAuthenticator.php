<?php

declare(strict_types=1);

namespace App\Shared\Application\Security;

use App\Shared\Domain\DomainException;

/**
 * How a context that services the machine checks a technician's credentials without depending on
 * the context that keeps the technicians.
 */
interface TechnicianAuthenticator
{
    /**
     * @return string the technician's id
     *
     * @throws DomainException when no technician has that username and password
     */
    public function authenticate(string $username, #[\SensitiveParameter] string $password): string;
}
