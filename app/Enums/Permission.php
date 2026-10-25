<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum Permission: string
{
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    case PermissionsView = 'permissions.view';
    case PermissionsCreate = 'permissions.create';
    case PermissionsDelete = 'permissions.delete';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';

    case DepartmentsView = 'departments.view';
    case DepartmentsCreate = 'departments.create';
    case DepartmentsUpdate = 'departments.update';
    case DepartmentsDelete = 'departments.delete';

    case PositionsView = 'positions.view';
    case PositionsCreate = 'positions.create';
    case PositionsUpdate = 'positions.update';
    case PositionsDelete = 'positions.delete';

    case EmployeesView = 'employees.view';
    case EmployeesCreate = 'employees.create';
    case EmployeesUpdate = 'employees.update';

    /**
     * Get the human readable label for each permission resource.
     *
     * @return array<string, string>
     */
    public static function resourceLabels(): array
    {
        return [
            'roles' => 'Roles',
            'permissions' => 'Permissions',
            'users' => 'Users',
            'departments' => 'Departments',
            'positions' => 'Positions',
            'employees' => 'Employees',
        ];
    }

    /**
     * Describe a single permission for the interface.
     *
     * @return array{name: string, label: string, group: string, group_label: string}
     */
    public static function describe(string $name): array
    {
        [$group, $action] = str_contains($name, '.')
            ? explode('.', $name, 2)
            : [$name, $name];

        return [
            'name' => $name,
            'label' => Str::headline($action),
            'group' => $group,
            'group_label' => self::resourceLabels()[$group] ?? Str::headline($group),
        ];
    }

    /**
     * Describe the given permission names for the interface.
     *
     * @param  iterable<int, string>  $names
     * @return array<int, array{name: string, label: string, group: string, group_label: string}>
     */
    public static function catalogue(iterable $names): array
    {
        return collect($names)
            ->unique()
            ->sort()
            ->map(fn (string $name): array => self::describe($name))
            ->values()
            ->all();
    }
}
