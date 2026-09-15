<?php

namespace App\Services\Applications;

use App\Models\JobSeeker;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;

final class ApplicantContactEligibility
{
    /**
     * Validate the canonical contact details used for an application.
     */
    public function validator(User $user, JobSeeker $jobSeeker): ValidatorContract
    {
        return Validator::make([
            'applicant_email' => $user->email,
            'applicant_phone' => $jobSeeker->phone,
        ], [
            'applicant_email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'applicant_phone' => $this->phoneRules(required: true),
        ], [
            'applicant_email.required' => 'Add an email address in account settings before applying.',
            'applicant_email.lowercase' => 'Update your email address in account settings before applying.',
            'applicant_email.email' => 'Update your email address in account settings before applying.',
            'applicant_phone.required' => 'Add a phone number to your applicant profile before applying.',
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    public function phoneRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }

                $phone = trim($value);

                if ($phone === '' || mb_strlen($phone) > 30 || ! $this->hasValidPhoneStructure($phone)) {
                    $fail('Enter a valid phone number.');
                }
            },
        ];
    }

    private function hasValidPhoneStructure(string $phone): bool
    {
        $base = $phone;

        if (preg_match('/\A(?<base>.*?)(?:\s*(?:x|ext\.?)\s*(?<extension>\d+))\z/i', $phone, $matches) === 1) {
            $base = trim($matches['base']);
        }

        if ($base === '' || substr_count($base, '+') > 1) {
            return false;
        }

        if (str_contains($base, '+') && ! str_starts_with($base, '+')) {
            return false;
        }

        if (substr_count($base, '(') > 1 || substr_count($base, ')') > 1) {
            return false;
        }

        $baseDigits = preg_replace('/\D/', '', $base);
        $baseDigitCount = strlen((string) $baseDigits);

        if ($baseDigitCount < 7 || $baseDigitCount > 20) {
            return false;
        }

        $baseWithoutParentheses = preg_replace('/\((\d+)\)/', '$1', $base);

        if (! is_string($baseWithoutParentheses) || str_contains($baseWithoutParentheses, '(') || str_contains($baseWithoutParentheses, ')')) {
            return false;
        }

        return preg_match('/\A\+?\d+(?:(?:\s+|\s*[-.]\s*)\d+)*\z/', $baseWithoutParentheses) === 1;
    }

    public function normalizePhone(mixed $phone): ?string
    {
        if (! is_string($phone)) {
            return null;
        }

        $phone = trim($phone);

        return $phone === '' ? null : $phone;
    }
}
