<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDomainSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('domain')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // US-SET-1 — recommended ≤365 (12 months) per GDPR; warn above, hard cap at 730.
            'consentExpiryDays' => ['required', 'integer', 'min:1', 'max:730'],
            'scheduledScanEnabled' => ['required', 'boolean'],
            'scanFrequency' => ['nullable', Rule::in(['weekly', 'monthly'])],
            // US-SET-3 — notification preferences.
            'newCookieAlerts' => ['required', 'boolean'],
        ];
    }

    /**
     * Enforce the tier's scheduled-scan entitlement (US-SCAN-5).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('scheduledScanEnabled')) {
                return;
            }

            $tier = $this->user()->resolveTier();

            if (! $tier->scheduled_scans_allowed) {
                $validator->errors()->add(
                    'scheduledScanEnabled',
                    sprintf('The %s tier does not include scheduled scans.', $tier->name),
                );
            }
        });
    }
}
