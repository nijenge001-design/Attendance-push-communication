<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $sites = [
            [
                'code' => 'HQ-KGL',
                'name' => 'Head Office – Kigali',
                'address' => 'KN 4 Ave',
                'city' => 'Kigali',
                'country' => 'Rwanda',
                'timezone' => 'Africa/Kigali',
                'is_active' => true,
            ],
            [
                'code' => 'BR-MUS',
                'name' => 'Branch – Musanze',
                'address' => 'Musanze Town',
                'city' => 'Musanze',
                'country' => 'Rwanda',
                'timezone' => 'Africa/Kigali',
                'is_active' => true,
            ],
            [
                'code' => 'BR-HUY',
                'name' => 'Branch – Huye',
                'address' => 'Huye Campus Area',
                'city' => 'Huye',
                'country' => 'Rwanda',
                'timezone' => 'Africa/Kigali',
                'is_active' => true,
            ],
            [
                'code' => 'WH-RWA',
                'name' => 'Warehouse – Rwamagana',
                'address' => 'Eastern Province',
                'city' => 'Rwamagana',
                'country' => 'Rwanda',
                'timezone' => 'Africa/Kigali',
                'is_active' => true,
            ],
            [
                'code' => 'OLD-RBV',
                'name' => 'Closed Site – Rubavu',
                'address' => null,
                'city' => 'Rubavu',
                'country' => 'Rwanda',
                'timezone' => 'Africa/Kigali',
                'is_active' => false,
            ],
        ];

        foreach ($sites as $data) {
            Site::query()->updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command?->info('Sites seeded: '.count($sites));
    }
}
