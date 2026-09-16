```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds protocol-aligned fields (mask/temperature) and critical indexes
 * for high-volume attendance + bio tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // attendance_logs – protocol fields + indexes
        // ------------------------------------------------------------------
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Protocol fields present in newer firmwares (2024 doc)
            if (! Schema::hasColumn('attendance_logs', 'mask_flag')) {
                $table->unsignedTinyInteger('mask_flag')->nullable()->after('type');
            }
            if (! Schema::hasColumn('attendance_logs', 'temperature')) {
                $table->decimal('temperature', 5, 2)->nullable()->after('mask_flag');
            }
            if (! Schema::hasColumn('attendance_logs', 'conv_temperature')) {
                $table->decimal('conv_temperature', 5, 2)->nullable()->after('temperature');
            }

            // Prevent duplicate punches from the same device
            $table->unique(
                ['device_serial', 'pin', 'timestamp'],
                'attendance_logs_device_pin_ts_unique'
            );

            $table->index('pin');
            $table->index('device_serial');
            $table->index('timestamp');
            $table->index(['device_serial', 'timestamp']);
            $table->index(['pin', 'timestamp']);
        });

        // ------------------------------------------------------------------
        // bio_templates – uniqueness + lookup indexes
        // ------------------------------------------------------------------
        Schema::table('bio_templates', function (Blueprint $table) {
            // A template is uniquely identified by device + pin + type + no + index
            $table->unique(
                ['device_serial', 'pin', 'type', 'no', 'index'],
                'bio_templates_device_pin_type_no_idx_unique'
            );

            $table->index('pin');
            $table->index(['pin', 'type']);
            $table->index('device_serial');
        });

        // ------------------------------------------------------------------
        // photos
        // ------------------------------------------------------------------
        Schema::table('photos', function (Blueprint $table) {
            $table->index('pin');
            $table->index('device_serial');
            $table->index(['device_serial', 'type']);
        });

        // ------------------------------------------------------------------
        // attendee_device – already has unique, add composite for active lookups
        // ------------------------------------------------------------------
        Schema::table('attendee_device', function (Blueprint $table) {
            $table->index(['device_serial', 'active']);
        });

        // ------------------------------------------------------------------
        // pending_commands – already well indexed, add sent_at for stuck queries
        // ------------------------------------------------------------------
        Schema::table('pending_commands', function (Blueprint $table) {
            $table->index('sent_at');
            $table->index(['executed', 'enabled', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique('attendance_logs_device_pin_ts_unique');
            $table->dropIndex(['pin']);
            $table->dropIndex(['device_serial']);
            $table->dropIndex(['timestamp']);
            $table->dropIndex(['device_serial', 'timestamp']);
            $table->dropIndex(['pin', 'timestamp']);

            if (Schema::hasColumn('attendance_logs', 'conv_temperature')) {
                $table->dropColumn('conv_temperature');
            }
            if (Schema::hasColumn('attendance_logs', 'temperature')) {
                $table->dropColumn('temperature');
            }
            if (Schema::hasColumn('attendance_logs', 'mask_flag')) {
                $table->dropColumn('mask_flag');
            }
        });

        Schema::table('bio_templates', function (Blueprint $table) {
            $table->dropUnique('bio_templates_device_pin_type_no_idx_unique');
            $table->dropIndex(['pin']);
            $table->dropIndex(['pin', 'type']);
            $table->dropIndex(['device_serial']);
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['pin']);
            $table->dropIndex(['device_serial']);
            $table->dropIndex(['device_serial', 'type']);
        });

        Schema::table('attendee_device', function (Blueprint $table) {
            $table->dropIndex(['device_serial', 'active']);
        });

        Schema::table('pending_commands', function (Blueprint $table) {
            $table->dropIndex(['sent_at']);
            $table->dropIndex(['executed', 'enabled', 'available_at']);
        });
    }
};
```
