<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\HybridBio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Matrix-style tests ensuring intersect() behaviour matches protocol intent.
 */
final class HybridBioIntersectMatrixTest extends TestCase
{
    #[Test]
    #[DataProvider('intersectCases')]
    public function intersect_matrix(string $server, string $device, array $expectedEnabled): void
    {
        $result = HybridBio::intersect($server, $device);
        $this->assertSame(
            $expectedEnabled,
            HybridBio::enabledTypes($result),
            "Failed for server={$server} device={$device} → {$result}"
        );
    }

    public static function intersectCases(): array
    {
        $fullServer = HybridBio::defaultDataSupport(); // 0:1:1:0:0:0:0:1:0:1:1

        return [
            'identical masks' => [
                '0:1:0:0:0:0:0:0:0:1:0',
                '0:1:0:0:0:0:0:0:0:1:0',
                [1, 9],
            ],
            'server subset of device' => [
                '0:1:0:0:0:0:0:0:0:1:0',
                '0:1:1:0:0:0:0:1:0:1:1',
                [1, 9],
            ],
            'device subset of server' => [
                $fullServer,
                '0:0:0:0:0:0:0:0:0:1:0',
                [9],
            ],
            'no overlap' => [
                '0:1:0:0:0:0:0:0:0:0:0',
                '0:0:0:0:0:0:0:0:0:1:0',
                [],
            ],
            'visible palm only on both' => [
                '0:0:0:0:0:0:0:0:0:0:1',
                '0:0:0:0:0:0:0:0:0:0:1',
                [10],
            ],
            'all types on both' => [
                '0:1:1:1:1:1:1:1:1:1:1',
                '0:1:1:1:1:1:1:1:1:1:1',
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            ],
            'real-world example from protocol doc' => [
                // Server: visible face only
                '0:0:0:0:0:0:0:0:0:1:0',
                // Device: FP + visible face + visible face photo (data mask)
                '0:1:0:0:0:0:0:0:0:1:0',
                [9],
            ],
        ];
    }
}
