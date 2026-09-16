<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pin');
            $table->string('device_serial');
            $table->timestamp('timestamp');
            $table->unsignedTinyInteger('status');
            $table->unsignedTinyInteger('verify_mode');
            $table->string('workcode')->nullable();
            $table->string('reserved1')->nullable();
            $table->string('reserved2')->nullable();
            $table->string('id_number')->nullable();
            $table->unsignedTinyInteger('type')->default(0);
            $table->unsignedTinyInteger('mask_flag')->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('conv_temperature', 5, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();


            // Prevent duplicate punches from the same device
            $table->unique(['device_serial', 'pin', 'timestamp'], 'attendance_logs_device_pin_ts_unique');
            $table->index('pin');
            $table->index('device_serial');
            $table->index('timestamp');
            $table->index(['device_serial', 'timestamp']);
            $table->index(['pin', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
