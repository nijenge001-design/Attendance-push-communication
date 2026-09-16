<?php

namespace App\Support;

/**
 * Hybrid Identification Protocol helpers (ZKTeco PUSH ≥ 2.4.1 / Doc ≥ 3.7).
 *
 * Biometric type index (Appendix 10):
 *   0  Common
 *   1  Fingerprint
 *   2  Near-infrared face
 *   3  Voiceprint
 *   4  Iris
 *   5  Retina
 *   6  Palmprint
 *   7  Finger vein
 *   8  Palm vein
 *   9  Visible light face
 *  10  Visible light palm
 *
 * MultiBio* parameters are colon-separated values, one slot per type index.
 * Example MultiBioDataSupport=0:1:0:0:0:0:0:0:0:1:0
 *   → supports Fingerprint (1) and Visible light face (9) templates.
 */
final class HybridBio
{
    public const int TYPE_COMMON          = 0;
    public const int TYPE_FINGERPRINT     = 1;
    public const int TYPE_NIR_FACE        = 2;
    public const int TYPE_VOICEPRINT      = 3;
    public const int TYPE_IRIS            = 4;
    public const int TYPE_RETINA          = 5;
    public const int TYPE_PALMPRINT       = 6;
    public const int TYPE_FINGER_VEIN     = 7;
    public const int TYPE_PALM_VEIN       = 8;
    public const int TYPE_VISIBLE_FACE    = 9;
    public const int TYPE_VISIBLE_PALM    = 10;

    /** Highest defined type index (inclusive). */
    public const int MAX_TYPE_INDEX = 10;

    /** Human-readable names keyed by type index. */
    public const array TYPE_NAMES = [
        self::TYPE_COMMON       => 'Common',
        self::TYPE_FINGERPRINT  => 'Fingerprint',
        self::TYPE_NIR_FACE     => 'Near-infrared face',
        self::TYPE_VOICEPRINT   => 'Voiceprint',
        self::TYPE_IRIS         => 'Iris',
        self::TYPE_RETINA       => 'Retina',
        self::TYPE_PALMPRINT    => 'Palmprint',
        self::TYPE_FINGER_VEIN  => 'Finger vein',
        self::TYPE_PALM_VEIN    => 'Palm vein',
        self::TYPE_VISIBLE_FACE => 'Visible light face',
        self::TYPE_VISIBLE_PALM => 'Visible light palm',
    ];

    /**
     * Parse a MultiBio* colon-separated string into an array of length MAX_TYPE_INDEX+1.
     * Missing slots become 0. Extra slots are ignored.
     *
     * @return list<int|string>
     */
    public static function parse(string $value): array
    {
        $parts = array_map('trim', explode(':', $value));
        $result = array_fill(0, self::MAX_TYPE_INDEX + 1, 0);

        foreach ($parts as $i => $part) {
            if ($i > self::MAX_TYPE_INDEX) {
                break;
            }
            // Keep non-zero version numbers / counts as-is (string or int).
            $result[$i] = is_numeric($part) ? (str_contains($part, '.') ? $part : (int) $part) : 0;
        }

        return $result;
    }

    /**
     * Build a colon-separated MultiBio* string from an array of values.
     *
     * @param  array<int, int|string|bool>  $slots
     */
    public static function build(array $slots, int $length = self::MAX_TYPE_INDEX + 1): string
    {
        $out = [];
        for ($i = 0; $i < $length; $i++) {
            $v = $slots[$i] ?? 0;
            $out[] = $v === true ? 1 : (int) $v;
        }

        return implode(':', $out);
    }

    /**
     * Logical AND of two MultiBio support masks (0/1 only).
     * Used to compute the intersection of device & server capabilities.
     */
    public static function intersect(string $a, string $b): string
    {
        $pa = self::parse($a);
        $pb = self::parse($b);
        $result = [];

        for ($i = 0; $i <= self::MAX_TYPE_INDEX; $i++) {
            $result[$i] = ((int) $pa[$i] > 0 && (int) $pb[$i] > 0) ? 1 : 0;
        }

        return self::build($result);
    }

    /**
     * Return list of type indexes that are enabled (non-zero) in the mask.
     *
     * @return list<int>
     */
    public static function enabledTypes(string $mask): array
    {
        $parsed = self::parse($mask);
        $types = [];

        foreach ($parsed as $index => $value) {
            if ((int) $value > 0) {
                $types[] = $index;
            }
        }

        return $types;
    }

    public static function typeName(int $type): string
    {
        return self::TYPE_NAMES[$type] ?? "Unknown({$type})";
    }

    /**
     * Whether the given type is a visible-light modality (9–10).
     */
    public static function isVisibleLight(int $type): bool
    {
        return $type >= self::TYPE_VISIBLE_FACE;
    }

    /**
     * Whether the given type is a near-infrared modality (1–8).
     */
    public static function isNearInfrared(int $type): bool
    {
        return $type >= self::TYPE_FINGERPRINT && $type <= self::TYPE_PALM_VEIN;
    }

    /**
     * Default server-side MultiBioDataSupport (templates).
     * Enables: Fingerprint, NIR Face, Finger vein, Visible light face, Visible light palm.
     */
    public static function defaultDataSupport(): string
    {
        return self::build([
            0 => 0, // Common
            1 => 1, // Fingerprint
            2 => 1, // NIR face
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 1, // Finger vein
            8 => 0,
            9 => 1, // Visible light face
            10 => 1, // Visible light palm
        ]);
    }

    /**
     * Default server-side MultiBioPhotoSupport (comparison photos).
     * Enables: Visible light face + Visible light palm (most common for modern devices).
     */
    public static function defaultPhotoSupport(): string
    {
        return self::build([
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
            8 => 0,
            9 => 1, // Visible light face
            10 => 1, // Visible light palm
        ]);
    }
}
