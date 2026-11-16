<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure\Controller;

use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticatedTechnicianResponse;
use App\Maintenance\Application\Query\AuthenticateTechnician\AuthenticateTechnicianQuery;
use App\Maintenance\Infrastructure\Security\TechnicianTokenIssuer;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class LoginController extends BaseController
{
    public function __construct(
        CommandBus $commandBus,
        QueryBus $queryBus,
        private readonly TechnicianTokenIssuer $tokenIssuer,
    ) {
        parent::__construct($commandBus, $queryBus);
    }

    public function __invoke(#[MapRequestPayload] LoginRequest $request): Response
    {
        $technician = $this->query(new AuthenticateTechnicianQuery($request->username, $request->password));
        \assert($technician instanceof AuthenticatedTechnicianResponse);

        return $this->json(['token' => $this->tokenIssuer->issueFor($technician)]);
    }
}
