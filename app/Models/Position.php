<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $department_id
 * @property-read int $assignments_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Department|null $department
 * @property-read Collection<int, EmploymentAssignment> $employmentAssignments
 */
#[Fillable(['name', 'department_id'])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    /**
     * Get the department the position belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get every assignment that references the position.
     *
     * @return HasMany<EmploymentAssignment, $this>
     */
    public function employmentAssignments(): HasMany
    {
        return $this->hasMany(EmploymentAssignment::class);
    }

    /**
     * Determine whether an employee assignment references this position.
     */
    public function isInUse(): bool
    {
        return $this->employmentAssignments()->exists();
    }
}
