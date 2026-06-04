<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBannerRequest extends FormRequest
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
        return [
            'layout' => ['required', 'array'],
            'layout.type' => ['required', Rule::in(['box', 'bar', 'popup'])],
            'layout.position' => ['required', 'string', 'max:32'],
            'layout.theme' => ['required', Rule::in(['light', 'dark'])],
            'layout.colors' => ['nullable', 'array'],
            'layout.logo' => ['nullable', 'string', 'max:2048'],

            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', 'max:12'],
            'default_language' => ['required', 'string', 'max:12'],

            'content' => ['required', 'array'],

            'policy_url' => ['nullable', 'url', 'max:2048'],
            'consent_mode_map' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $languages = (array) $this->input('languages', []);
            $default = $this->input('default_language');

            if ($default && ! in_array($default, $languages, true)) {
                $validator->errors()->add('default_language', 'Default language must be one of the selected languages.');
            }

            // Every selected language must have a content entry.
            $content = (array) $this->input('content', []);
            foreach ($languages as $lang) {
                if (! array_key_exists($lang, $content)) {
                    $validator->errors()->add('content', "Missing content for language \"{$lang}\".");
                }
            }
        });
    }
}
