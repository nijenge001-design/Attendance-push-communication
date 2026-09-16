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
        Schema::create('pending_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_serial')->index();
            $table->string('command_id')->unique()->index();
            $table->text('command_text');
            $table->boolean('executed')->default(false)->index();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('enabled_at')->nullable();
            $table->json('result')->nullable();
            $table->integer('return_code')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('available_at')->nullable()->index(); // when it becomes eligible again
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('recurrence')->nullable(); // cron expression or simple keyword
            $table->timestamp('next_run_at')->nullable()->index();
            $table->boolean('is_recurring')->default(false);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['device_serial', 'executed']);
            $table->index('sent_at');
            $table->index(['executed', 'enabled', 'available_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_commands');
    }
};
