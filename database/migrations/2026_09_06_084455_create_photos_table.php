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
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_serial');
            $table->string('pin')->nullable();
            $table->string('filename');
            $table->tinyInteger('type')->nullable(); // 0=common,1=finger,2=face,...
            $table->longText('content')->nullable();  // base64 or binary
            $table->string('url')->nullable();
            $table->softDeletes();
            $table->timestamps();


            $table->index('pin');
            $table->index('device_serial');
            $table->index(['device_serial', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
