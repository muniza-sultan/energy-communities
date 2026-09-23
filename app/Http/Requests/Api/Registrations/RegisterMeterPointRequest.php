<?php

namespace App\Http\Requests\Api\Registrations;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMeterPointRequest extends FormRequest
{
    /**
     * BR-5: only managers (or admins). Checked before validation, so others get 403.
     */
    public function authorize(): bool
    {
        return $this->user()->can('registerMeterPoint', $this->route('energyCommunity'));
    }

    /**
     * 
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [

            'meter_point_id' => ['required', 'integer', Rule::exists('meter_points', 'id')->withoutTrashed()],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],

            'consent_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], // in future
        ];
    }

    public function validatedDate(string $key): ?CarbonImmutable
    {
        $value = $this->validated($key);

        return $value === null ? null : CarbonImmutable::createFromFormat('!Y-m-d', $value);
    }
}
