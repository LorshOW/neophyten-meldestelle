<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development accounts only. DatabaseSeeder runs this outside production.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Super Admin', 'superadmin@meldestelle.test', Roles::SUPER_ADMIN],
            ['Administration', 'admin@meldestelle.test', Roles::ADMIN],
            ['Innendienst Person', 'innendienst@meldestelle.test', Roles::INNENDIENST],
            ['Außendienst Person', 'aussendienst@meldestelle.test', Roles::AUSSENDIENST],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
