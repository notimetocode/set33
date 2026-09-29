<?php

namespace App\Http\Requests\App\Site;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteDocumentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document' => [
                'nullable',
                'file',
                'max:512',
                'extensions:md,txt',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название документа.',
            'document.max' => 'Размер файла не должен превышать 512 КБ.',
            'document.extensions' => 'Допустимы только файлы .md или .txt.',
        ];
    }
}
