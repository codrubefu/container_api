<?php

namespace App\Http\Requests\Users;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Deactivating through PATCH follows
     * the same rule as DELETE (nobody can deactivate themselves).
     */
    public function authorize(): bool
    {
        if ($this->has('is_active') && ! $this->boolean('is_active')) {
            return $this->user()->can('delete', $this->route('user'));
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['sometimes', 'string', 'max:32', 'regex:/^\+?[0-9 ]{9,20}$/'],
            'password' => ['sometimes', 'string', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
