<?php

namespace App\Http\Requests\App\AiService;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAiServiceContentRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Введите промпт.',
            'prompt.max' => 'Промпт слишком длинный (максимум 10 000 символов).',
        ];
    }
}
