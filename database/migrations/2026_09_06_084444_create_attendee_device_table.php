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
        Schema::create('attendee_device', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->string('device_serial');

            // Optional: extra per-device settings
            $table->integer('privilege')->nullable();   // override global privilege on this device
            $table->integer('group_id')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['attendee_id', 'device_serial']);
            $table->index('device_serial');

            $table->index(['device_serial', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendee_device');
    }
};
