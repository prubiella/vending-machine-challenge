<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Controller;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticateTechnicianQuery;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class LoginController extends BaseController
{
    private const string TECHNICIAN_ROLE = 'ROLE_TECHNICIAN';

    public function __construct(
        CommandBus $commandBus,
        QueryBus $queryBus,
        private readonly JWTTokenManagerInterface $tokenManager,
    ) {
        parent::__construct($commandBus, $queryBus);
    }

    public function __invoke(#[MapRequestPayload] LoginRequest $request): Response
    {
        $technician = $this->query(new AuthenticateTechnicianQuery($request->username, $request->password));
        \assert($technician instanceof AuthenticatedTechnicianResponse);

        $token = $this->tokenManager->create(new InMemoryUser($technician->technicianId, null, [self::TECHNICIAN_ROLE]));

        return $this->json(['token' => $token]);
    }
}
