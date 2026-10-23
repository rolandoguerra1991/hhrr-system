<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            'Executive' => [],
            'Operations' => ['Warehouse', 'Logistics'],
            'Sales' => ['Customer Support'],
            'Finance' => [],
            'Human Resources' => [],
        ];

        foreach ($departments as $parentName => $childNames) {
            $parent = Department::query()->firstOrCreate([
                'name' => $parentName,
                'parent_id' => null,
            ]);

            foreach ($childNames as $childName) {
                Department::query()->firstOrCreate([
                    'name' => $childName,
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}
