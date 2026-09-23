<?php

namespace App\Http\Requests\Api\EnergyCommunities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnergyCommunityRequest extends FormRequest
{
    /**
     * Authorization happens in the controller via EnergyCommunityPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     *
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Unique.
            'ecid' => ['required', 'string', 'max:255', Rule::unique('energy_communities', 'ecid')],
            'name' => ['nullable', 'string', 'max:255'],
            //'state' :  no need as it will always be new
        ];
    }
}
