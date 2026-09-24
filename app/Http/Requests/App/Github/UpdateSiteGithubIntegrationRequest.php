<?php

namespace App\Http\Requests\App\Github;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteGithubIntegrationRequest extends FormRequest
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
        return [
            'repository_full_name' => ['required', 'string', 'max:255', 'regex:/^[^\/\s]+\/[^\/\s]+$/'],
            'repository_id' => ['nullable', 'integer', 'min:1'],
            'default_branch' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'repository_full_name.required' => 'Выберите репозиторий.',
            'repository_full_name.regex' => 'Некорректное имя репозитория.',
            'default_branch.required' => 'Выберите ветку.',
        ];
    }
}
