<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        if (! app()->environment(['local', 'development'])) {
            $this->command->warn('Skipping demo departments, positions, and employees outside the local or development environment.');

            return;
        }

        $this->call([
            DepartmentSeeder::class,
            PositionSeeder::class,
            EmployeeSeeder::class,
        ]);
    }
}
