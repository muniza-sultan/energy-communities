<?php

namespace App\Http\Requests\Api\Registrations;

use App\Enums\EnergyCommunityMeterPointState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionRegistrationRequest extends FormRequest
{
    /**
     * BR-9: a manager of the community (or an admin). Checked before validation.
     */
    public function authorize(): bool
    {
        return $this->user()->can('transition', $this->route('registration'));
    }

    /**
     * Body: { "state": "error", "status_code": 4711 }  (status_code only for -> error)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', Rule::enum(EnergyCommunityMeterPointState::class)],
            'status_code' => [
                'required_if:state,error',
                'prohibited_unless:state,error',
                'nullable',
                'integer',
            ],
        ];
    }

    public function targetState(): EnergyCommunityMeterPointState
    {
        return EnergyCommunityMeterPointState::from($this->validated('state'));
    }
}
