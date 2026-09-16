<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Minimal demo set (sites + users + a couple of devices + few attendees).
 * Use when you do not want hundreds of attendance rows.
 *
 *   php artisan db:seed --class=DemoLightSeeder
 */
class DemoLightSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SiteSeeder::class,
            UserSeeder::class,
            DeviceSeeder::class,
        ]);

        // Only fixed attendees, no mass logs
        $this->call(AttendeeSeeder::class);

        $this->command?->info('Light demo seeded (no bulk attendance logs / bio templates).');
    }
}
