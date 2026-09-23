<?php

namespace App\Http\Requests\Api\EnergyCommunities;

use App\Enums\EnergyCommunityUserRole;
use App\Models\EnergyCommunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddEnergyCommunityUserRequest extends FormRequest
{
    /**
     * BR-4: only managers (or admins)..
     */
    public function authorize(): bool
    {
        return $this->user()->can('addUser', $this->energyCommunity());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('users', 'id'),
                // BR-4: at most one role per user and community 
                Rule::unique('energy_community_user', 'user_id')
                    ->where('energy_community_id', $this->energyCommunity()->id),
            ],
            'role' => ['required', Rule::enum(EnergyCommunityUserRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'The user already exists in this energy community.',
        ];
    }

    private function energyCommunity(): EnergyCommunity
    {
        return $this->route('energyCommunity');
    }
}
