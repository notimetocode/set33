<?php

namespace App\Http\Requests\App\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'telegram' => ['nullable', 'string', 'max:64'],
            'viber' => ['nullable', 'string', 'max:64'],
            'locale' => [
                'required',
                'string',
                Rule::in(config('localization.available', ['en', 'ru'])),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('validation.required', ['attribute' => 'e-mail']),
            'email.email' => __('validation.email', ['attribute' => 'e-mail']),
            'email.unique' => __('validation.unique', ['attribute' => 'e-mail']),
            'locale.required' => __('validation.required', ['attribute' => 'locale']),
            'locale.in' => __('validation.in', ['attribute' => 'locale']),
        ];
    }
}
