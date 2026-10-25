<?php

namespace App\Http\Requests\Position;

use App\Concerns\PositionValidationRules;
use App\Models\Position;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePositionRequest extends FormRequest
{
    use PositionValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $position = $this->route('position');

        return $this->positionRules($position instanceof Position ? $position : null);
    }

    /**
     * Get the validation callbacks that guard positions with assignment history.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        $position = $this->route('position');

        return $this->positionScopeGuards($position instanceof Position ? $position : null);
    }

    /**
     * Get the submitted department identifier, treating a blank value as company-wide.
     */
    public function departmentId(): ?int
    {
        return $this->filled('department_id') ? $this->integer('department_id') : null;
    }
}
