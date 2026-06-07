<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTierRequest extends FormRequest
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
        $tierId = $this->route('tier')?->id;

        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('tiers', 'slug')->ignore($tierId)],
            'max_domains' => ['nullable', 'integer', 'min:1'],            // null = unlimited
            'max_scan_pages' => ['required', 'integer', 'min:1', 'max:100000'],
            'monthly_pageview_cap' => ['nullable', 'integer', 'min:0'],   // null = unlimited
            'scheduled_scans_allowed' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}
