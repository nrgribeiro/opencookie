<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is gated by role:super_admin.
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tier_id' => ['nullable', 'integer', 'exists:tiers,id'],
            'is_super_admin' => ['required', 'boolean'],
        ];
    }
}
