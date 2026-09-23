<?php

namespace App\Http\Requests\Api\MeterPoints;

use App\Enums\EnergyDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMeterPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An invalid filter value will return error.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'energy_direction' => ['sometimes', Rule::enum(EnergyDirection::class)],
        ];
    }
}
