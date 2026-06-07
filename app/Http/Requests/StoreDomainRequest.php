<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalize hostname before validation: strip scheme, path, port, lowercase.
     */
    protected function prepareForValidation(): void
    {
        $host = trim((string) $this->input('hostname'));
        $host = preg_replace('#^https?://#i', '', $host);   // strip scheme
        $host = preg_replace('#[/:].*$#', '', $host);        // strip path / port
        $host = strtolower(rtrim($host, '.'));

        $this->merge(['hostname' => $host]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'hostname' => [
                'required',
                'string',
                'max:253',
                // Valid registrable domain (no scheme/path), at least one dot + TLD.
                'regex:/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/',
                'unique:domains,hostname',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'hostname.regex' => 'Enter a valid domain like example.com (no http:// or paths).',
            'hostname.unique' => 'That domain is already registered.',
        ];
    }

    /**
     * Enforce the tier domain cap (US-ADMIN-5). A null max means unlimited.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tier = $this->user()->resolveTier();
            $max = $tier->max_domains;

            if ($max !== null && $this->user()->domains()->count() >= $max) {
                $validator->errors()->add(
                    'hostname',
                    $max === 1
                        ? sprintf('The %s tier allows 1 domain. Remove the existing domain to add another.', $tier->name)
                        : sprintf('The %s tier allows up to %d domains. Remove one to add another.', $tier->name, $max),
                );
            }
        });
    }
}
