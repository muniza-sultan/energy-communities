<?php

namespace App\Http\Requests\Api\Registrations;

use App\Enums\EnergyCommunityMeterPointState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRegistrationRequest extends FormRequest
{
    /**
     * BR-11: members (either role) and admins see a community's registrations.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('energyCommunity'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['sometimes', Rule::enum(EnergyCommunityMeterPointState::class)],
        ];
    }
}
