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
        return [
            'ai_service_id' => [
                'required',
                'integer',
                Rule::exists('ai_services', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
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
    }
}
