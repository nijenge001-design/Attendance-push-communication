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
        Schema::create('attendee_import_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_serial')->index();
            $table->string('pin')->index();
            $table->string('name')->nullable();
            $table->string('card_number')->nullable()->index();
            $table->string('vice_card')->nullable();
            $table->integer('privilege')->nullable();
            $table->integer('group_id')->nullable();
            $table->string('timezone')->nullable();
            $table->integer('verification_mode')->nullable();
            $table->string('password')->nullable();
            $table->string('error_code')->nullable()->index(); // e.g. duplicate_card
            $table->text('error_message');
            $table->json('raw_data')->nullable();
            $table->string('status')->default('pending')->index(); // pending, resolved, ignored
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendee_import_errors');
    }
};
