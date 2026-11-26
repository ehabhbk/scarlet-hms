<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            OrganizationSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
        ]);

        if (config('scarlet.seed_demo_data')) {
            $this->call(DemoClinicalSeeder::class);
        }

        $this->call(CatalogSeeder::class);
    }
}
