<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'role_id' => ['required', 'integer'],
            'staff_profile_id' => ['nullable', 'integer'],
            'location_ids' => ['array'],
            'location_ids.*' => ['integer'],
            'expires_in_days' => ['sometimes', 'integer', 'between:1,30'],
        ];
    }
}
