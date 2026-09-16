<?php

declare(strict_types=1);

namespace Tests\Feature\Hybrid;

use App\Models\Device;
use App\Support\HybridBio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Hybrid Identification Protocol end-to-end behaviour.
 *
 * Covers:
 *  - Handshake emits MultiBioDataSupport / MultiBioPhotoSupport
 *  - Device options push (table=options) is stored
 *  - Subsequent handshake uses intersection of capabilities
 *  - Device model helpers reflect negotiated types
 */
final class HybridIdentificationTest extends TestCase
{
    use RefreshDatabase;

    private function approveDevice(string $sn = 'TESTSN001'): Device
    {
        $device = Device::factory()->create([
            'serial_number' => $sn,
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        return $device;
    }

    public function test_handshake_includes_multi_bio_when_protocol_high_enough(): void
    {
        $this->approveDevice('HYB001');

        $response = $this->get('/iclock/cdata?SN=HYB001&options=all&pushver=2.4.2&PushOptionsFlag=1');

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('GET OPTION FROM: HYB001', $body);
        $this->assertStringContainsString('MultiBioDataSupport=', $body);
        $this->assertStringContainsString('MultiBioPhotoSupport=', $body);
        $this->assertStringContainsString('PushOptionsFlag=1', $body);
        $this->assertStringContainsString('SupportPing=1', $body);
    }

    public function test_handshake_omits_multi_bio_for_old_protocol(): void
    {
        $this->approveDevice('HYB002');

        // Force a very old negotiated version by advertising low pushver
        // and temporarily lowering server max via config
        config(['iclock.push_prot_ver' => '2.2.14']);
        config(['iclock.multi_bio_min_ver' => '2.4.1']);

        $response = $this->get('/iclock/cdata?SN=HYB002&options=all&pushver=2.2.14');

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('GET OPTION FROM: HYB002', $body);
        $this->assertStringNotContainsString('MultiBioDataSupport=', $body);
        $this->assertStringNotContainsString('MultiBioPhotoSupport=', $body);
    }

    public function test_disabled_handshake_for_pending_device_has_no_multi_bio(): void
    {
        Device::factory()->create([
            'serial_number' => 'PEND001',
            'status' => Device::STATUS_PENDING,
        ]);

        $response = $this->get('/iclock/cdata?SN=PEND001&options=all&pushver=2.4.2');

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('GET OPTION FROM: PEND001', $body);
        $this->assertStringContainsString('TransFlag=0', $body);
        $this->assertStringNotContainsString('MultiBioDataSupport=', $body);
    }

    public function test_options_push_stores_hybrid_identification_parameters(): void
    {
        $device = $this->approveDevice('HYB003');

        $payload = implode("\n", [
            'MultiBioDataSupport=0:1:0:0:0:0:0:0:0:1:1',
            'MultiBioPhotoSupport=0:0:0:0:0:0:0:0:0:1:0',
            'MultiBioVersion=0:10:0:0:0:0:0:0:0:3:0',
            'MaxMultiBioDataCount=0:5000:0:0:0:0:0:0:0:2000:0',
            'FingerFunOn=1',
            'FaceFunOn=0',
            'BioPhotoFun=1',
            'VisilightFun=1',
        ]);

        $response = $this->call(
            'POST',
            '/iclock/cdata?SN=HYB003&table=options',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $payload
        );

        $response->assertOk();
        $this->assertSame('OK', trim($response->getContent()));

        $device->refresh();
        $caps = $device->capabilities;

        $this->assertSame('0:1:0:0:0:0:0:0:0:1:1', $caps['multi_bio_data_support']);
        $this->assertSame('0:0:0:0:0:0:0:0:0:1:0', $caps['multi_bio_photo_support']);
        $this->assertSame('0:10:0:0:0:0:0:0:0:3:0', $caps['multi_bio_version']);
        // FunOn keys are stored lowercased by handlePushOptions
        $this->assertSame('1', $caps['fingerfunon'] ?? null);
        $this->assertTrue((bool) $device->finger_fun_on);
        $this->assertTrue((bool) $device->photo_fun_on);
        $this->assertArrayHasKey('options_pushed_at', $caps);
    }

    public function test_subsequent_handshake_uses_intersection_of_capabilities(): void
    {
        $device = $this->approveDevice('HYB004');

        // Server default data: 0:1:1:0:0:0:0:1:0:1:1  (FP, NIR, FV, VisFace, VisPalm)
        // Device reports:      0:1:0:0:0:0:0:0:0:1:0  (FP + VisFace only)
        $device->forceFill([
            'capabilities' => [
                'multi_bio_data_support' => '0:1:0:0:0:0:0:0:0:1:0',
                'multi_bio_photo_support' => '0:0:0:0:0:0:0:0:0:1:0',
            ],
        ])->save();

        $response = $this->get('/iclock/cdata?SN=HYB004&options=all&pushver=2.4.2&PushOptionsFlag=1');

        $response->assertOk();
        $body = $response->getContent();

        // Extract MultiBioDataSupport line
        preg_match('/MultiBioDataSupport=([^\n\r]+)/', $body, $m);
        $this->assertNotEmpty($m[1] ?? null, 'MultiBioDataSupport missing from handshake');

        $effective = trim($m[1]);
        $enabled = HybridBio::enabledTypes($effective);

        // Intersection should be FP (1) + Visible face (9) only
        $this->assertSame([1, 9], $enabled);
        $this->assertSame('0:1:0:0:0:0:0:0:0:1:0', $effective);
    }

    public function test_device_helpers_reflect_supported_types(): void
    {
        $device = $this->approveDevice('HYB005');

        $device->forceFill([
            'capabilities' => [
                'multi_bio_data_support' => '0:1:0:0:0:0:0:0:0:1:1',
                'multi_bio_photo_support' => '0:0:0:0:0:0:0:0:0:1:0',
            ],
        ])->save();

        $this->assertTrue($device->supportsBioDataType(HybridBio::TYPE_FINGERPRINT));
        $this->assertTrue($device->supportsBioDataType(HybridBio::TYPE_VISIBLE_FACE));
        $this->assertTrue($device->supportsBioDataType(HybridBio::TYPE_VISIBLE_PALM));
        $this->assertFalse($device->supportsBioDataType(HybridBio::TYPE_NIR_FACE));

        $this->assertTrue($device->supportsBioPhotoType(HybridBio::TYPE_VISIBLE_FACE));
        $this->assertFalse($device->supportsBioPhotoType(HybridBio::TYPE_VISIBLE_PALM));

        $this->assertSame(
            [HybridBio::TYPE_FINGERPRINT, HybridBio::TYPE_VISIBLE_FACE, HybridBio::TYPE_VISIBLE_PALM],
            $device->supportedBioDataTypes()
        );
    }

    public function test_attlog_and_biodata_still_accepted_after_hybrid_handshake(): void
    {
        $this->approveDevice('HYB006');

        // Handshake first
        $this->get('/iclock/cdata?SN=HYB006&options=all&pushver=2.4.2')->assertOk();

        // Minimal ATTLOG line: Pin HT Time HT Status HT Verify
        $attlog = "1001\t2026-09-12 10:00:00\t0\t1\n";
        $r1 = $this->call(
            'POST',
            '/iclock/cdata?SN=HYB006&table=ATTLOG&Stamp=0',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $attlog
        );
        $r1->assertOk();
        $this->assertStringStartsWith('OK', trim($r1->getContent()));

        // Minimal BIODATA-style line (handler is tolerant)
        $biodata = "BIODATA Pin=1001\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=9\tMajorVer=3\tMinorVer=0\tFormat=0\tTmp=ABC123\n";
        $r2 = $this->call(
            'POST',
            '/iclock/cdata?SN=HYB006&table=BIODATA&Stamp=0',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $biodata
        );
        $r2->assertOk();
        $this->assertStringStartsWith('OK', trim($r2->getContent()));
    }

    public function test_missing_sn_returns_400(): void
    {
        $this->get('/iclock/cdata?options=all')->assertStatus(400);
    }

    public function test_invalid_sn_returns_400(): void
    {
        $this->get('/iclock/cdata?SN=bad/sn!&options=all')->assertStatus(400);
    }
}
