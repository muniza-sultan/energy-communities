<?php

namespace App\Http\Requests\Api\EnergyCommunities;

use App\Enums\EnergyCommunityState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEnergyCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     *  state filter
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['sometimes', Rule::enum(EnergyCommunityState::class)],
        ];
    }
}
