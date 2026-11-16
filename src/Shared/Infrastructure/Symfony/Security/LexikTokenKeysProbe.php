<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use App\Shared\Application\Query\HealthCheck\TokenKeysProbe;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Psr\Log\LoggerInterface;

// A public key of another pair must fail the probe, which is why it verifies what it signs.
final readonly class LexikTokenKeysProbe implements TokenKeysProbe
{
    public function __construct(
        private JWSProviderInterface $jwsProvider,
        private LoggerInterface $logger,
    ) {
    }

    public function isUsable(): bool
    {
        try {
            $signed = $this->jwsProvider->create(['readiness_probe' => true]);
            if (!$signed->isSigned()) {
                return $this->notUsable('the private key cannot sign');
            }
            if (!$this->jwsProvider->load($signed->getToken())->isVerified()) {
                return $this->notUsable('the public key does not verify what the private key signs');
            }

            return true;
        } catch (\Exception $exception) {
            return $this->notUsable($exception->getMessage(), $exception);
        }
    }

    private function notUsable(string $reason, ?\Exception $exception = null): bool
    {
        $this->logger->warning('The JWT key pair is not usable: {reason}', ['reason' => $reason, 'exception' => $exception]);

        return false;
    }
}
