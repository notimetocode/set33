<?php

namespace App\Http\Requests\App\Google;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncSiteGoogleIntegrationRequest extends FormRequest
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

            if ($from->diffInDays($to) > 365) {
                $validator->errors()->add('to', 'Период не должен превышать 365 дней.');
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
