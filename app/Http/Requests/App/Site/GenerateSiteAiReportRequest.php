<?php

namespace App\Http\Requests\App\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSiteAiReportRequest extends FormRequest
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
        $useSystemPrompt = $this->boolean('use_system_prompt', true);

        return [
            'ai_service_id' => [
                'required',
                'integer',
                Rule::exists('ai_services', 'id')->where(
                    fn ($query) => $query->where(function ($scoped): void {
                        $scoped->where('user_id', $this->user()->id)
                            ->orWhere('is_global', true);
                    }),
                ),
            ],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'use_system_prompt' => ['sometimes', 'boolean'],
            'prompt' => [
                Rule::excludeIf($useSystemPrompt),
                Rule::requiredIf(! $useSystemPrompt),
                'string',
                'min:1',
                'max:10000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ai_service_id.required' => 'Выберите AI-сервис.',
            'ai_service_id.exists' => 'Выбранный AI-сервис недоступен.',
            'from.required' => 'Укажите дату начала.',
            'to.required' => 'Укажите дату окончания.',
            'to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
            'prompt.required' => 'Введите промпт.',
            'prompt.min' => 'Введите промпт.',
            'prompt.max' => 'Промпт слишком длинный (максимум 10 000 символов).',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('from')) {
            $this->merge(['from' => now()->subDays(27)->toDateString()]);
        }

        if (! $this->filled('to')) {
            $this->merge(['to' => now()->subDay()->toDateString()]);
        }

        if (! $this->exists('use_system_prompt')) {
            $this->merge(['use_system_prompt' => true]);
        }
    }
}
