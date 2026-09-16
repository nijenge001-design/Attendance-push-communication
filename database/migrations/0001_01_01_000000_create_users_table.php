<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique()->nullable();
            $table->string('phone_number')->unique();
            $table->string('password');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->enum('role', UserRole::values())->default(UserRole::Viewer->value);
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        // Insert system admin by default
        DB::table('users')->insert([
            "id" =>Str::uuid()->toString(),
            "name" => "Joseph Niyonizeye",
            "username" => "nijoseph37",
            "email" => "niyo.joseph37@gmail.com",
            "phone_number" => "0788909194",
            "password" => Hash::make('ChangeMe@123'),
            "phone_verified_at" => Carbon::now(),
            "email_verified_at" => Carbon::now(),
            "password_changed_at" => Carbon::now(),
            "role" => UserRole::Admin,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);


        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
