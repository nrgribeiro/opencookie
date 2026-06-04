<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDomainRequest extends FormRequest
{
    /** Free-tier cap (functional-spec §8): 1 domain per account. */
    private const FREE_TIER_DOMAIN_LIMIT = 1;

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
     * Enforce the free-tier domain cap.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->user()->domains()->count() >= self::FREE_TIER_DOMAIN_LIMIT) {
                $validator->errors()->add(
                    'hostname',
                    'Free tier allows 1 domain. Remove the existing domain to add another.',
                );
            }
        });
    }
}
