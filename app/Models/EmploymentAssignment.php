<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EmploymentAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $department_id
 * @property int|null $position_id
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string $change_reason
 * @property int|null $changed_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Employee $employee
 * @property-read Department $department
 * @property-read Position|null $position
 * @property-read User|null $changedBy
 */
#[Fillable(['employee_id', 'department_id', 'position_id', 'effective_from', 'effective_to', 'change_reason', 'changed_by'])]
class EmploymentAssignment extends Model
{
    /** @use HasFactory<EmploymentAssignmentFactory> */
    use HasFactory;

    /**
     * Get the employee this assignment belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the department assigned to the employee.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the position held by the employee, if one is set.
     *
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the user who recorded the assignment.
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Determine whether the assignment is still current.
     */
    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
