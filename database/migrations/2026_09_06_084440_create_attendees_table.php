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
        Schema::create('attendees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pin')->unique();
            $table->string('name')->nullable();
            $table->integer('privilege')->default(0);
            $table->string('password')->nullable();
            $table->string('card_number')->nullable()->unique()->index();
            $table->string('vice_card')->nullable()->index();
            $table->integer('group_id')->default(1);
            $table->string('timezone')->nullable();
            $table->integer('verification_mode')->default(-1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
