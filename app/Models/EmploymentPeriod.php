<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EmploymentPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property CarbonImmutable $started_on
 * @property CarbonImmutable|null $ended_on
 * @property string $start_reason
 * @property string|null $termination_reason
 * @property int|null $created_by
 * @property int|null $terminated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Employee $employee
 * @property-read User|null $creator
 * @property-read User|null $terminator
 */
#[Fillable(['employee_id', 'started_on', 'ended_on', 'start_reason', 'termination_reason', 'created_by', 'terminated_by'])]
class EmploymentPeriod extends Model
{
    /** @use HasFactory<EmploymentPeriodFactory> */
    use HasFactory;

    /**
     * Get the employee this period belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who created the period.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who terminated the period.
     *
     * @return BelongsTo<User, $this>
     */
    public function terminator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }

    /**
     * Determine whether the period is still open.
     */
    public function isOpen(): bool
    {
        return $this->ended_on === null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }
}
