<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyWide = [
            'Chief Executive Officer',
            'Chief Financial Officer',
            'Chief Operating Officer',
        ];

        $byDepartment = [
            'Executive' => ['Executive Assistant'],
            'Operations' => [
                'Operations Manager',
                'Warehouse Associate',
                'Logistics Coordinator',
            ],
            'Sales' => [
                'Sales Manager',
                'Sales Representative',
                'Customer Support Specialist',
            ],
            'Finance' => [
                'Finance Manager',
                'Accountant',
                'Accounts Payable Clerk',
            ],
            'Human Resources' => ['HR Manager', 'Recruiter'],
        ];

        foreach ($companyWide as $name) {
            Position::query()->firstOrCreate([
                'name' => $name,
                'department_id' => null,
            ]);
        }

        foreach ($byDepartment as $departmentName => $positionNames) {
            $department = Department::query()
                ->whereNull('parent_id')
                ->where('name', $departmentName)
                ->firstOrFail();

            foreach ($positionNames as $name) {
                Position::query()->firstOrCreate([
                    'name' => $name,
                    'department_id' => $department->id,
                ]);
            }
        }
    }
}
