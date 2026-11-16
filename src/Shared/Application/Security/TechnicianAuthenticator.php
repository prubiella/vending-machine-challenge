<?php

declare(strict_types=1);

namespace App\Shared\Application\Security;

// VendingMachine checks a technician's credentials through this port, since no context uses
// another; Maintenance implements it. It gives the technician's id, and refuses wrong credentials
// with a DomainException.
interface TechnicianAuthenticator
{
    public function authenticate(string $username, #[\SensitiveParameter] string $password): string;
}
