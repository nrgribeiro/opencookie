<?php

namespace App\Http\Requests;

use App\Enums\VerificationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDomainRequest extends FormRequest
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
            'method' => ['required', Rule::enum(VerificationMethod::class)],
        ];
    }

    public function method(): VerificationMethod
    {
        return VerificationMethod::from($this->validated('method'));
    }
}
