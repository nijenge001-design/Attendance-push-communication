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
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('serial_number')->unique();
            $table->string('device_name')->nullable();
            $table->string('status')->default('pending'); // pending, approved, blocked
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->string('ip')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('push_version')->nullable();
            $table->string('platform')->nullable();
            $table->string('language')->nullable();

            $table->unsignedInteger('user_count')->nullable();
            $table->unsignedInteger('fp_count')->nullable();
            $table->unsignedInteger('face_count')->nullable();
            $table->unsignedInteger('attlog_count')->nullable();

            $table->boolean('finger_fun_on')->nullable();
            $table->boolean('face_fun_on')->nullable();
            $table->boolean('photo_fun_on')->nullable();

            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('last_heartbeat_source')->nullable(); // ping, getrequest, cdata

            $table->json('capabilities')->nullable();

            $table->foreignUuid('site_id')->nullable()->constrained('sites')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
