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
        Schema::create('bio_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_serial');
            $table->string('pin');
            $table->tinyInteger('type');       // 1=finger, 2=face, ...
            $table->tinyInteger('no')->default(0);
            $table->tinyInteger('index')->default(0);
            $table->tinyInteger('valid')->default(1);
            $table->tinyInteger('duress')->default(0);
            $table->string('major_ver')->nullable();
            $table->string('minor_ver')->nullable();
            $table->string('format')->nullable();
            $table->text('template_data');
            $table->softDeletes();
            $table->timestamps();


            $table->unique(['device_serial', 'pin', 'type', 'no', 'index'], 'bio_templates_device_pin_type_no_idx_unique');

            $table->index('pin');
            $table->index(['pin', 'type']);
            $table->index('device_serial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bio_templates');
    }
};
