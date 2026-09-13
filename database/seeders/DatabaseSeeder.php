<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            WorkflowSeeder::class,
            SpeciesSeeder::class,
            EmailTemplateSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoUsersSeeder::class);
        }
    }
}
