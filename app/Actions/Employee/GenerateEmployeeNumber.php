<?php

namespace App\Actions\Employee;

use App\Models\Sequence;

class GenerateEmployeeNumber
{
    /**
     * Allocate the next employee number from the locked sequence.
     */
    public function execute(): string
    {
        $sequence = Sequence::query()
            ->where('name', 'employee_number')
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->value++;
        $sequence->save();

        return 'EMP-'.str_pad((string) $sequence->value, 4, '0', STR_PAD_LEFT);
    }
}
