<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Range;

final readonly class IntervalMerger
{
    /**
     * @param list<array{int, int}> $in
     *
     * @return list<array{int, int}>
     */
    public function run(array $in): array
    {
        usort($in, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $r = [];
        foreach ($in as $a) {
            if ([] !== $r) {
                $l = \count($r) - 1;
                if ($a[0] <= $r[$l][1]) {
                    if ($a[1] > $r[$l][1]) {
                        $r[$l][1] = $a[1];
                    }

                    continue;
                }
            }
            $r[] = $a;
        }

        return $r;
    }
}
