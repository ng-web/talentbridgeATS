<?php

namespace App\Http\Requests\Admin;

use App\Models\Entitlement;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'in:' . implode(',', Entitlement::TYPES)],
            'status' => ['required', 'in:' . implode(',', Entitlement::STATUSES)],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}