<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiDocumentTest extends KernelTestCase
{
    private const array HTTP_METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function testDocumentsEveryRouteOfTheApplicationAndNothingElse(): void
    {
        $documented = array_keys(self::operations());
        sort($documented);

        self::assertSame(self::routedOperations(), $documented);
    }

    public function testAnswersEveryOperationWithASuccessStatus(): void
    {
        foreach (self::operations() as $operation => $definition) {
            $statuses = array_map('strval', array_keys(self::at($definition, 'responses')));

            self::assertNotSame([], preg_grep('/^2\d\d$/', $statuses), $operation.' documents no 2xx response');
        }
    }

    public function testDocumentsEveryStatusThatFrameworkExceptionsMap(): void
    {
        $documented = [];
        foreach (self::operations() as $definition) {
            foreach (array_keys(self::at($definition, 'responses')) as $status) {
                $documented[(string) $status] = true;
            }
        }

        foreach (array_unique(self::statusesMappedByFramework()) as $status) {
            self::assertArrayHasKey((string) $status, $documented, \sprintf('No operation documents the %d the exceptions mapping has', $status));
        }
    }

    public function testGivesEveryBodyASchemaAndAnExample(): void
    {
        foreach (self::operations() as $operation => $definition) {
            foreach (self::mediaTypesByLocation($operation, $definition) as $location => $mediaTypes) {
                foreach ($mediaTypes as $mediaType) {
                    self::assertHasSchemaAndExample($location, self::node($mediaType));
                }
            }
        }
    }

    public function testEveryMachineOperationDocumentsA500AndEveryMachineChangeA409(): void
    {
        foreach (self::operations() as $operation => $definition) {
            if (!self::isMachineOperation($operation)) {
                continue;
            }
            $statuses = array_map('strval', array_keys(self::at($definition, 'responses')));

            self::assertContains('500', $statuses, $operation.' documents no 500');
            if (str_starts_with($operation, 'POST ')) {
                self::assertContains('409', $statuses, $operation.' changes the machine and documents no 409 for a concurrent update');
            }
        }
    }

    public function testEveryReferenceResolves(): void
    {
        $references = [];
        $document = self::document();
        array_walk_recursive($document, static function (mixed $value, string|int $key) use (&$references): void {
            if ('$ref' === $key) {
                $references[] = $value;
            }
        });

        self::assertNotSame([], $references);
        foreach ($references as $reference) {
            self::assertIsString($reference);
            self::assertNotNull(self::pointer($reference), $reference.' does not resolve');
        }
    }

    /**
     * @return list<string> "METHOD /path", sorted
     */
    private static function routedOperations(): array
    {
        self::bootKernel();
        $operations = [];
        foreach (self::getContainer()->get('router')->getRouteCollection() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (!\is_string($controller) || !str_starts_with($controller, 'App\\')) {
                continue;
            }
            self::assertNotSame([], $route->getMethods(), \sprintf('The route %s declares no method, so the document cannot be checked against it', $name));
            foreach ($route->getMethods() as $method) {
                $operations[] = $method.' '.$route->getPath();
            }
        }
        sort($operations);

        return $operations;
    }

    private static function isMachineOperation(string $operation): bool
    {
        return str_contains($operation, ' /machine');
    }

    /**
     * @return array<string, array<mixed>> the operations by "METHOD /path"
     */
    private static function operations(): array
    {
        $operations = [];
        foreach (self::at(self::document(), 'paths') as $path => $pathItem) {
            foreach (self::httpOperationsOf(self::node($pathItem)) as $method => $definition) {
                $operations[strtoupper($method).' '.$path] = $definition;
            }
        }

        return $operations;
    }

    /**
     * @param array<mixed> $pathItem
     *
     * @return array<string, array<mixed>> the operations by method; a path item also holds keys that are not
     *                                     operations, such as `parameters` and `summary`
     */
    private static function httpOperationsOf(array $pathItem): array
    {
        return array_map(self::node(...), array_intersect_key($pathItem, array_flip(self::HTTP_METHODS)));
    }

    /**
     * @param array<mixed> $operation
     *
     * @return array<string, array<mixed>> the media types of the request and of each response, by where they are, such as "POST /machine/coins 200"
     */
    private static function mediaTypesByLocation(string $name, array $operation): array
    {
        $bodies = [$name.' request' => self::at($operation, 'requestBody', 'content')];
        foreach (self::at($operation, 'responses') as $status => $response) {
            $bodies[$name.' '.$status] = self::at(self::resolve(self::node($response)), 'content');
        }

        return $bodies;
    }

    /**
     * @param array<mixed> $mediaType
     */
    private static function assertHasSchemaAndExample(string $location, array $mediaType): void
    {
        self::assertArrayHasKey('schema', $mediaType, $location.' has no schema');
        self::assertTrue(isset($mediaType['example']) || isset($mediaType['examples']), $location.' has no example');
    }

    /**
     * @return array<mixed>
     */
    private static function document(): array
    {
        return self::node(Yaml::parseFile(self::projectDir().'/docs/openapi.yaml'));
    }

    /**
     * @param array<mixed> $node
     *
     * @return array<mixed>
     */
    private static function resolve(array $node): array
    {
        $reference = $node['$ref'] ?? null;
        if (!\is_string($reference)) {
            return $node;
        }

        return self::node(self::pointer($reference), $reference.' does not resolve');
    }

    private static function pointer(string $reference): mixed
    {
        if (!str_starts_with($reference, '#/')) {
            return null;
        }

        $node = self::document();
        foreach (explode('/', substr($reference, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (!\array_key_exists($segment, $node)) {
                return null;
            }
            $next = $node[$segment];
            if (!\is_array($next)) {
                return $next;
            }
            $node = $next;
        }

        return $node;
    }

    /**
     * @return list<int>
     */
    private static function statusesMappedByFramework(): array
    {
        $framework = self::node(Yaml::parseFile(self::projectDir().'/config/packages/framework.yaml'));
        $mapping = self::at($framework, 'framework', 'exceptions');

        self::assertNotSame([], $mapping, 'framework.exceptions maps no exception');

        $statuses = [];
        foreach ($mapping as $class => $config) {
            $status = self::node($config)['status_code'] ?? null;
            self::assertIsInt($status, $class.' maps no status');
            $statuses[] = $status;
        }

        return $statuses;
    }

    /**
     * @param array<mixed> $node
     *
     * @return array<mixed> what is under the path, or nothing when a step is missing
     */
    private static function at(array $node, string ...$path): array
    {
        foreach ($path as $key) {
            $node = self::node($node[$key] ?? []);
        }

        return $node;
    }

    /**
     * @return array<mixed>
     */
    private static function node(mixed $value, string $message = 'Expected a YAML mapping or list'): array
    {
        self::assertIsArray($value, $message);

        return $value;
    }

    private static function projectDir(): string
    {
        return \dirname(__DIR__, 4);
    }
}
