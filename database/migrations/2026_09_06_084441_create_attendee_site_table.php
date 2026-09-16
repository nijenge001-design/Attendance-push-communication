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
        Schema::create('attendee_site', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignUuid('attendee_id')->constrained('attendees')->cascadeOnDelete();


            $table->boolean('is_active')->default(true);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('meta')->nullable();

            $table->unique(['site_id', 'attendee_id']);
            $table->index(['site_id', 'is_active']);


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendee_site');
    }
};
