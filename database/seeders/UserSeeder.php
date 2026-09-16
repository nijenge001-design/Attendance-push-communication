<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // System admin is inserted by the users migration; ensure a known demo set exists.
        $users = [
            [
                'name' => 'Demo Admin',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'phone_number' => '0788000001',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
            [
                'name' => 'Demo Manager',
                'username' => 'manager',
                'email' => 'manager@example.com',
                'phone_number' => '0788000002',
                'password' => Hash::make('password'),
                'role' => UserRole::Manager,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
            [
                'name' => 'Demo Operator',
                'username' => 'operator',
                'email' => 'operator@example.com',
                'phone_number' => '0788000003',
                'password' => Hash::make('password'),
                'role' => UserRole::Operator,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
            [
                'name' => 'Demo Viewer',
                'username' => 'viewer',
                'email' => 'viewer@example.com',
                'phone_number' => '0788000004',
                'password' => Hash::make('password'),
                'role' => UserRole::Viewer,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
            [
                'name' => 'Integration Bot',
                'username' => 'integration',
                'email' => 'integration@example.com',
                'phone_number' => '0788000005',
                'password' => Hash::make('password'),
                'role' => UserRole::Integration,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
        ];

        foreach ($users as $data) {
            User::query()->updateOrCreate(
                ['username' => $data['username']],
                array_merge(['id' => (string) Str::uuid()], $data)
            );
        }

        // Attach non-admin users to HQ site when present
        $hq = Site::query()->where('code', 'HQ-KGL')->first();
        if ($hq) {
            foreach (['manager', 'operator', 'viewer'] as $username) {
                $user = User::query()->where('username', $username)->first();
                if ($user && ! $user->allSites()->where('sites.id', $hq->id)->exists()) {
                    $user->allSites()->attach($hq->id, [
                        'id' => (string) Str::uuid(),
                        'is_active' => true,
                    ]);
                }
            }
        }

        $this->command?->info('Demo users seeded (password: password)');
    }
}
