<?php

namespace App\Http\Requests;

use App\Enums\CookieCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCookieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $assignable = array_map(fn (CookieCategory $c) => $c->value, CookieCategory::assignable());

        return [
            'category' => ['required', Rule::in($assignable)],
            'provider' => ['nullable', 'string', 'max:255'],
            'providerUrl' => ['nullable', 'url', 'max:2048'],
            'retention' => ['nullable', 'string', 'max:255'],
            'dataController' => ['nullable', 'string', 'max:255'],
            'gdprPortalUrl' => ['nullable', 'url', 'max:2048'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            // US-DECL-3 — { "<lang>": "<text>" } map.
            'purposeTranslations' => ['nullable', 'array'],
            'purposeTranslations.*' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
