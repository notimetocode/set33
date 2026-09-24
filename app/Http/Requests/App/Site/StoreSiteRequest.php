<?php

namespace App\Http\Requests\App\Site;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название сайта.',
            'url.required' => 'Укажите URL сайта.',
            'url.url' => 'Укажите корректный URL (например, https://example.com).',
        ];
    }
}
