<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Controller;

use Symfony\Component\Yaml\Yaml;

/**
 * The document is written by hand, so its examples can drift from what the API answers: this
 * compares the fields of each documented success body with the real one.
 */
final class OpenApiExamplesTest extends VendingMachineApiTestCase
{
    public function testTheExampleOfTheMachineHasTheFieldsOfTheRealBody(): void
    {
        $this->client->request('GET', '/machine');

        self::assertSame(self::shape(self::documentedExample('/machine')), self::shape($this->responseBody()));
    }

    public function testTheExampleOfATrayWithContentsHasTheFieldsOfTheRealBody(): void
    {
        $this->insertCoins('1');
        $this->client->request('POST', '/machine/selections/WATER');
        $this->client->request('GET', '/machine/tray');

        self::assertSame(self::shape(self::documentedExample('/machine/tray')), self::shape($this->responseBody()));
    }

    private static function documentedExample(string $path): mixed
    {
        $content = self::at(Yaml::parseFile(\dirname(__DIR__, 5).'/docs/openapi.yaml'), 'paths', $path, 'get', 'responses', '200', 'content', 'application/json');

        if (isset($content['example'])) {
            return $content['example'];
        }
        $examples = self::at($content, 'examples');
        self::assertNotSame([], $examples, $path.' documents no example');

        return self::at($examples['afterAPurchase'] ?? reset($examples))['value'] ?? null;
    }

    /**
     * @return array<mixed>
     */
    private static function at(mixed $node, string ...$keys): array
    {
        foreach ($keys as $key) {
            self::assertIsArray($node);
            self::assertArrayHasKey($key, $node);
            $node = $node[$key];
        }
        self::assertIsArray($node);

        return $node;
    }

    /**
     * The names of the fields at every depth, and the shape of the first item of a list.
     */
    private static function shape(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return 'scalar';
        }
        if (array_is_list($value)) {
            return [] === $value ? [] : ['*' => self::shape($value[0])];
        }
        ksort($value);

        return array_map(self::shape(...), $value);
    }
}
