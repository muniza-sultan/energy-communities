<?php

namespace App\Http\Requests\Api\MeterPoints;

use App\Enums\EnergyDirection;
use App\Rules\KnownGridOperator;
use App\Rules\MeterPointCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeterPointRequest extends FormRequest
{
    /**
     * Authorization happens in the controller via MeterPointPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // BR-1.: stop at the first failure, so a malformed code never triggers the operator lookup or the uniqueness query.
            'name' => [
                'bail',
                'required',
                'string',
                new MeterPointCode, //1
                new KnownGridOperator, //2
                Rule::unique('meter_points', 'name'), //3
            ],
            'energy_direction' => ['required', Rule::enum(EnergyDirection::class)],
        ];
    }
}
