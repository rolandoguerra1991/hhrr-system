<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $employee_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $work_email
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $full_name
 * @property-read Employee|null $reporting_manager
 * @property-read User|null $user
 * @property-read EmploymentPeriod|null $currentPeriod
 * @property-read EmploymentAssignment|null $currentAssignment
 * @property-read Collection<int, EmploymentPeriod> $employmentPeriods
 * @property-read Collection<int, EmploymentAssignment> $employmentAssignments
 * @property-read Collection<int, EmployeeChangeLog> $changeLogs
 * @property-read Collection<int, Department> $departmentsManaged
 */
#[Fillable(['employee_number', 'first_name', 'last_name', 'work_email', 'user_id'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * Get the login account linked to the employee.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get every employment period for the employee.
     *
     * @return HasMany<EmploymentPeriod, $this>
     */
    public function employmentPeriods(): HasMany
    {
        return $this->hasMany(EmploymentPeriod::class);
    }

    /**
     * Get the open employment period, if one exists.
     *
     * @return HasOne<EmploymentPeriod, $this>
     */
    public function currentPeriod(): HasOne
    {
        return $this->hasOne(EmploymentPeriod::class)->whereNull('ended_on');
    }

    /**
     * Get every department assignment for the employee.
     *
     * @return HasMany<EmploymentAssignment, $this>
     */
    public function employmentAssignments(): HasMany
    {
        return $this->hasMany(EmploymentAssignment::class);
    }

    /**
     * Get the open department assignment, if one exists.
     *
     * @return HasOne<EmploymentAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(EmploymentAssignment::class)->whereNull('effective_to');
    }

    /**
     * Get the core edit audit entries for the employee.
     *
     * @return HasMany<EmployeeChangeLog, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(EmployeeChangeLog::class);
    }

    /**
     * Get the departments this employee manages.
     *
     * @return HasMany<Department, $this>
     */
    public function departmentsManaged(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    /**
     * Get the employee's display name.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * Get the reporting manager from the current department.
     *
     * @return Attribute<Employee|null, never>
     */
    protected function reportingManager(): Attribute
    {
        return Attribute::get(fn (): ?Employee => $this->currentAssignment?->department?->manager);
    }

    /**
     * Determine whether the employee has an open employment period.
     */
    public function isActive(): bool
    {
        return $this->currentPeriod()->exists();
    }

    /**
     * Determine whether the employee has no open employment period.
     */
    public function isTerminated(): bool
    {
        return ! $this->isActive();
    }
}
