<?php

namespace App\Http\Requests\Ingest;

use App\Enums\ConsentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConsentRequest extends FormRequest
{
    private const ALLOWED_CATEGORIES = ['necessary', 'preferences', 'statistics', 'marketing'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'consentId' => ['nullable', 'uuid'],
            'method' => ['required', Rule::enum(ConsentMethod::class)],
            'bannerVersion' => ['required', 'integer', 'min:1'],
            'policyVersion' => ['required', 'integer', 'min:1'],
            'categories' => ['required', 'array'],
            'categories.necessary' => ['required', 'boolean'],
            'categories.preferences' => ['sometimes', 'boolean'],
            'categories.statistics' => ['sometimes', 'boolean'],
            'categories.marketing' => ['sometimes', 'boolean'],
            'consentTextHash' => ['nullable', 'string', 'max:128'],
            'language' => ['nullable', 'string', 'max:12'],
            'ts' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unknown = array_diff(array_keys((array) $this->input('categories', [])), self::ALLOWED_CATEGORIES);
            if (! empty($unknown)) {
                $validator->errors()->add('categories', 'Unknown category keys: '.implode(', ', $unknown));
            }
        });
    }
}
