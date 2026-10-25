<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 * @property int|null $manager_id
 * @property-read int $employees_count
 * @property-read int $assignments_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Department|null $parent
 * @property-read Employee|null $manager
 * @property-read Collection<int, Position> $positions
 * @property-read Collection<int, EmploymentAssignment> $employmentAssignments
 * @property-read Collection<int, EmploymentAssignment> $currentAssignments
 */
#[Fillable(['name', 'parent_id', 'manager_id'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * Get the parent department.
     *
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child departments, ordered by name.
     *
     * @return HasMany<Department, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /**
     * Get the employee who manages the department.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Get the positions attached to the department.
     *
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Get every employment assignment recorded for the department.
     *
     * @return HasMany<EmploymentAssignment, $this>
     */
    public function employmentAssignments(): HasMany
    {
        return $this->hasMany(EmploymentAssignment::class);
    }

    /**
     * Get the assignments that are currently in effect.
     *
     * @return HasMany<EmploymentAssignment, $this>
     */
    public function currentAssignments(): HasMany
    {
        return $this->hasMany(EmploymentAssignment::class)->whereNull('effective_to');
    }
}
