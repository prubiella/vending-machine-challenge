<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure;

use App\Shared\Domain\DomainException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A domain exception answers 500 unless `framework.exceptions` maps it to a status. The ones that
 * must answer 500 are listed in the `app.domain_exceptions_answering_500` parameter, next to that
 * map, so that a new exception has to be put on one side or the other.
 */
final class DomainExceptionStatusTest extends KernelTestCase
{
    public function testEveryDomainExceptionIsMappedToAStatusOrListedAsAnswering500(): void
    {
        $unclassified = [];
        foreach (self::domainExceptions() as $class) {
            if (500 === self::statusOf($class) && !\in_array($class, self::answering500(), true)) {
                $unclassified[] = $class;
            }
        }

        self::assertSame([], $unclassified, 'Map each one in framework.exceptions, or list it in app.domain_exceptions_answering_500');
    }

    public function testListsOnlyDomainExceptionsThatAnswer500(): void
    {
        $stale = [];
        foreach (self::answering500() as $class) {
            if (!\in_array($class, self::domainExceptions(), true) || 500 !== self::statusOf($class)) {
                $stale[] = $class;
            }
        }

        self::assertSame([], $stale, 'Remove from app.domain_exceptions_answering_500 what no longer exists or has a status');
    }

    /**
     * @return list<class-string<DomainException>>
     */
    private static function domainExceptions(): array
    {
        $classes = [];
        $sources = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::projectDir().'/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($sources as $file) {
            \assert($file instanceof \SplFileInfo);
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $class = self::classNameOf($file);
            if (self::isFinalDomainException($class)) {
                $classes[] = $class;
            }
        }
        sort($classes);

        self::assertNotSame([], $classes);

        return $classes;
    }

    private static function classNameOf(\SplFileInfo $file): string
    {
        $relativePath = substr($file->getPathname(), \strlen(self::projectDir().'/src/'), -\strlen('.php'));

        return 'App\\'.str_replace('/', '\\', $relativePath);
    }

    /**
     * @phpstan-assert-if-true class-string<DomainException> $class
     */
    private static function isFinalDomainException(string $class): bool
    {
        return class_exists($class) && is_subclass_of($class, DomainException::class) && new \ReflectionClass($class)->isFinal();
    }

    /**
     * @return list<string>
     */
    private static function answering500(): array
    {
        self::bootKernel();

        return array_values(self::getContainer()->getParameter('app.domain_exceptions_answering_500'));
    }

    /**
     * @param class-string<DomainException> $class
     */
    private static function statusOf(string $class): int
    {
        $exception = new \ReflectionClass($class)->newInstanceWithoutConstructor();
        $event = new ExceptionEvent(self::bootKernel(), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);

        self::getContainer()->get(EventDispatcherInterface::class)->dispatch($event, KernelEvents::EXCEPTION);

        return $event->getResponse()?->getStatusCode() ?? self::fail($class.' got no response');
    }

    private static function projectDir(): string
    {
        return \dirname(__DIR__, 4);
    }
}
