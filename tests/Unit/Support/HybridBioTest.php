<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\HybridBio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for Hybrid Identification Protocol helper (Appendix 10).
 */
final class HybridBioTest extends TestCase
{
    #[Test]
    public function default_data_support_mask_is_correct_length_and_enables_expected_types(): void
    {
        $mask = HybridBio::defaultDataSupport();
        $parsed = HybridBio::parse($mask);

        $this->assertCount(HybridBio::MAX_TYPE_INDEX + 1, $parsed);
        $this->assertSame(0, (int) $parsed[0]); // Common off
        $this->assertSame(1, (int) $parsed[1]); // Fingerprint
        $this->assertSame(1, (int) $parsed[2]); // NIR face
        $this->assertSame(1, (int) $parsed[7]); // Finger vein
        $this->assertSame(1, (int) $parsed[9]); // Visible face
        $this->assertSame(1, (int) $parsed[10]); // Visible palm
    }

    #[Test]
    public function default_photo_support_enables_visible_light_types_only(): void
    {
        $mask = HybridBio::defaultPhotoSupport();
        $enabled = HybridBio::enabledTypes($mask);

        $this->assertContains(HybridBio::TYPE_VISIBLE_FACE, $enabled);
        $this->assertContains(HybridBio::TYPE_VISIBLE_PALM, $enabled);
        $this->assertNotContains(HybridBio::TYPE_FINGERPRINT, $enabled);
        $this->assertNotContains(HybridBio::TYPE_NIR_FACE, $enabled);
    }

    #[Test]
    public function parse_pads_and_truncates_correctly(): void
    {
        $parsed = HybridBio::parse('0:1:1');
        $this->assertCount(11, $parsed);
        $this->assertSame(0, $parsed[0]);
        $this->assertSame(1, $parsed[1]);
        $this->assertSame(1, $parsed[2]);
        $this->assertSame(0, $parsed[10]); // padded

        $long = HybridBio::parse('0:1:0:0:0:0:0:0:0:1:1:99:88');
        $this->assertCount(11, $long);
        $this->assertSame(1, $long[10]);
    }

    #[Test]
    public function build_roundtrips_with_parse(): void
    {
        $original = [0, 1, 0, 0, 0, 0, 0, 1, 0, 1, 1];
        $built = HybridBio::build($original);
        $this->assertSame('0:1:0:0:0:0:0:1:0:1:1', $built);
        $this->assertSame($original, array_map('intval', HybridBio::parse($built)));
    }

    #[Test]
    public function intersect_returns_logical_and_of_two_masks(): void
    {
        // Device: FP + Visible face + Visible palm
        $device = '0:1:0:0:0:0:0:0:0:1:1';
        // Server: FP + NIR face + Finger vein + Visible face
        $server = '0:1:1:0:0:0:0:1:0:1:0';

        $result = HybridBio::intersect($server, $device);
        $enabled = HybridBio::enabledTypes($result);

        $this->assertSame([1, 9], $enabled); // only FP + Visible face
        $this->assertSame('0:1:0:0:0:0:0:0:0:1:0', $result);
    }

    #[Test]
    public function intersect_with_empty_or_zero_mask_yields_nothing(): void
    {
        $this->assertSame(
            '0:0:0:0:0:0:0:0:0:0:0',
            HybridBio::intersect(HybridBio::defaultDataSupport(), '0:0:0:0:0:0:0:0:0:0:0')
        );
    }

    #[Test]
    public function enabled_types_returns_only_nonzero_indexes(): void
    {
        $this->assertSame(
            [1, 9, 10],
            HybridBio::enabledTypes('0:1:0:0:0:0:0:0:0:1:1')
        );
        $this->assertSame([], HybridBio::enabledTypes('0:0:0:0:0:0:0:0:0:0:0'));
    }

    #[Test]
    #[DataProvider('typeNameProvider')]
    public function type_name_returns_correct_label(int $type, string $expected): void
    {
        $this->assertSame($expected, HybridBio::typeName($type));
    }

    public static function typeNameProvider(): array
    {
        return [
            [0, 'Common'],
            [1, 'Fingerprint'],
            [2, 'Near-infrared face'],
            [7, 'Finger vein'],
            [9, 'Visible light face'],
            [10, 'Visible light palm'],
            [99, 'Unknown(99)'],
        ];
    }

    #[Test]
    public function is_visible_light_and_near_infrared_helpers(): void
    {
        $this->assertTrue(HybridBio::isVisibleLight(9));
        $this->assertTrue(HybridBio::isVisibleLight(10));
        $this->assertFalse(HybridBio::isVisibleLight(1));
        $this->assertFalse(HybridBio::isVisibleLight(2));

        $this->assertTrue(HybridBio::isNearInfrared(1));
        $this->assertTrue(HybridBio::isNearInfrared(8));
        $this->assertFalse(HybridBio::isNearInfrared(9));
        $this->assertFalse(HybridBio::isNearInfrared(0));
    }

    #[Test]
    public function parse_preserves_non_binary_version_and_count_values(): void
    {
        // MultiBioVersion style: non-zero means algorithm version
        $parsed = HybridBio::parse('0:10:7:0:0:0:0:0:0:3:0');
        $this->assertSame(10, $parsed[1]);
        $this->assertSame(7, $parsed[2]);
        $this->assertSame(3, $parsed[9]);
    }

    #[Test]
    public function constants_match_appendix_10(): void
    {
        $this->assertSame(0, HybridBio::TYPE_COMMON);
        $this->assertSame(1, HybridBio::TYPE_FINGERPRINT);
        $this->assertSame(2, HybridBio::TYPE_NIR_FACE);
        $this->assertSame(7, HybridBio::TYPE_FINGER_VEIN);
        $this->assertSame(9, HybridBio::TYPE_VISIBLE_FACE);
        $this->assertSame(10, HybridBio::TYPE_VISIBLE_PALM);
        $this->assertSame(10, HybridBio::MAX_TYPE_INDEX);
    }
}
