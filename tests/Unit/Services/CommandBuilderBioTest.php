<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CommandBuilder;
use App\Support\HybridBio;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ensures CommandBuilder emits correct unified BIODATA / BioPhoto commands
 * for Hybrid Identification types (including type 9 & 10).
 */
final class CommandBuilderBioTest extends TestCase
{
    #[Test]
    public function update_bio_data_includes_type_and_template_fields(): void
    {
        $cmd = CommandBuilder::updateBioData([
            'pin' => '1001',
            'no' => 0,
            'index' => 0,
            'valid' => 1,
            'duress' => 0,
            'type' => HybridBio::TYPE_VISIBLE_FACE,
            'majorver' => '3',
            'minorver' => '0',
            'format' => 0,
            'tmp' => 'BASE64TMP',
        ]);

        $this->assertStringContainsString('DATA UPDATE BIODATA', $cmd);
        $this->assertStringContainsString('Pin=1001', $cmd);
        $this->assertStringContainsString('Type=9', $cmd);
        $this->assertStringContainsString('Tmp=BASE64TMP', $cmd);
    }

    #[Test]
    public function update_bio_data_supports_visible_palm_type_10(): void
    {
        $cmd = CommandBuilder::updateBioData([
            'pin' => '2002',
            'type' => HybridBio::TYPE_VISIBLE_PALM,
            'tmp' => 'PALMDATA',
        ]);

        $this->assertStringContainsString('Type=10', $cmd);
        $this->assertStringContainsString('Pin=2002', $cmd);
    }

    #[Test]
    public function update_bio_photo_includes_type_and_optional_postback(): void
    {
        $cmd = CommandBuilder::updateBioPhoto(
            pin: '1001',
            type: HybridBio::TYPE_VISIBLE_FACE,
            contentBase64: 'PHOTO64',
            postBackTmpFlag: 1
        );

        $this->assertStringContainsString('DATA UPDATE', $cmd);
        $this->assertStringContainsString('PIN=1001', $cmd);
        $this->assertStringContainsString('Type=9', $cmd);
        $this->assertStringContainsString('Content=PHOTO64', $cmd);
        $this->assertStringContainsString('PostBackTmpFlag=1', $cmd);
    }

    #[Test]
    public function delete_bio_data_can_target_type(): void
    {
        $cmd = CommandBuilder::deleteBioData('1001', HybridBio::TYPE_VISIBLE_FACE, 0);
        $this->assertStringContainsString('DATA DELETE BIODATA', $cmd);
        $this->assertStringContainsString('PIN=1001', $cmd);  // was Pin=
        $this->assertStringContainsString('Type=9', $cmd);
        $this->assertStringContainsString('No=0', $cmd);
    }

    #[Test]
    public function query_bio_data_includes_type(): void
    {
        $cmd = CommandBuilder::queryBioData(HybridBio::TYPE_FINGERPRINT, '1001');
        $this->assertStringContainsString('DATA QUERY BIODATA', $cmd);
        $this->assertStringContainsString('Type=1', $cmd);
    }

    #[Test]
    public function generate_command_prefixes_with_c_and_id(): void
    {
        $full = CommandBuilder::generateCommand('DATA UPDATE USERINFO Pin=1');
        $this->assertMatchesRegularExpression('/^C:[^:]+:DATA UPDATE USERINFO Pin=1$/', $full);
    }
}
