<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BioTemplate;
use App\Support\HybridBio;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ensures BioTemplate type constants stay aligned with Hybrid Identification (Appendix 10).
 */
final class BioTemplateTypeTest extends TestCase
{
    #[Test]
    public function type_constants_match_hybrid_bio_and_protocol(): void
    {
        $this->assertSame(HybridBio::TYPE_COMMON, BioTemplate::TYPE_GENERAL);
        $this->assertSame(HybridBio::TYPE_FINGERPRINT, BioTemplate::TYPE_FINGER);
        $this->assertSame(HybridBio::TYPE_NIR_FACE, BioTemplate::TYPE_FACE);
        $this->assertSame(HybridBio::TYPE_FINGER_VEIN, BioTemplate::TYPE_FINGER_VEIN);
        $this->assertSame(HybridBio::TYPE_VISIBLE_FACE, BioTemplate::TYPE_VISIBLE_FACE);
        $this->assertSame(HybridBio::TYPE_VISIBLE_PALM, BioTemplate::TYPE_VISIBLE_PALM);
    }

    #[Test]
    public function visible_palm_constant_exists_and_is_ten(): void
    {
        $this->assertSame(10, BioTemplate::TYPE_VISIBLE_PALM);
    }
}
