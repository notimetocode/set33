<?php

namespace App\Http\Requests\App\Site;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteEventRequest extends FormRequest
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
            'occurred_on' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'occurred_on.required' => 'Укажите дату события.',
            'occurred_on.date' => 'Укажите корректную дату события.',
            'title.required' => 'Укажите название события.',
            'url.url' => 'Укажите корректный URL (например, https://example.com).',
        ];
    }
}
