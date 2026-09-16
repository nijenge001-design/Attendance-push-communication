<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with demo / test data.
     *
     * Order matters: sites → users → devices → attendees → logs / biometrics.
     */
    public function run(): void
    {
        $this->call([
            SiteSeeder::class,
            UserSeeder::class,
            DeviceSeeder::class,
            AttendeeSeeder::class,
            AttendanceLogSeeder::class,
            BioTemplateSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Demo data ready.');
        $this->command?->table(
            ['Login', 'Password', 'Role'],
            [
                ['admin / admin@example.com', 'password', 'Admin'],
                ['manager', 'password', 'Manager'],
                ['operator', 'password', 'Operator'],
                ['viewer', 'password', 'Viewer'],
                ['integration', 'password', 'Integration'],
            ]
        );
        $this->command?->info('Approved device SNs: CKJG0001, CKJG0002, CKMS0001, CKHY0001');
        $this->command?->info('Pending: PENDING01 | Blocked: BLOCKED01');
        $this->command?->info('Sample employee PINs: 1001–1005, 9001');
    }
}
