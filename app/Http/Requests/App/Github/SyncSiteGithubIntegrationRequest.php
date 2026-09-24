<?php

namespace App\Http\Requests\App\Github;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncSiteGithubIntegrationRequest extends FormRequest
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
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:'.now()->subDay()->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'Укажите дату начала периода.',
            'to.required' => 'Укажите дату окончания периода.',
            'to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
            'to.before_or_equal' => 'Дата окончания не может быть позже вчерашнего дня.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = $this->date('from');
            $to = $this->date('to');

            if ($from === null || $to === null) {
                return;
            }

            if ($from->diffInDays($to) > 90) {
                $validator->errors()->add('to', 'Период не должен превышать 90 дней.');
            }
        });
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
