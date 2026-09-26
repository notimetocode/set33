<?php

namespace App\Http\Requests\App\Google;

use App\Support\SiteSyncMetrics;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*' => ['required', 'string', Rule::in(SiteSyncMetrics::google())],
            'limits' => ['nullable', 'array'],
            'limits.queries' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'limits.pages' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'limits.url_inspections' => ['nullable', 'integer', 'min:1', 'max:50'],
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
            'metrics.required' => 'Выберите хотя бы одну метрику для загрузки.',
            'metrics.min' => 'Выберите хотя бы одну метрику для загрузки.',
            'metrics.*.in' => 'Выбрана неизвестная метрика.',
            'limits.queries.min' => 'Количество запросов должно быть не меньше 1.',
            'limits.queries.max' => 'Количество запросов не должно превышать 1000.',
            'limits.pages.min' => 'Количество страниц должно быть не меньше 1.',
            'limits.pages.max' => 'Количество страниц не должно превышать 1000.',
            'limits.url_inspections.min' => 'Количество URL для проверки должно быть не меньше 1.',
            'limits.url_inspections.max' => 'Количество URL для проверки не должно превышать 50.',
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

    /**
     * @return list<string>
     */
    public function metrics(): array
    {
        /** @var list<string> $metrics */
        $metrics = array_values(array_unique($this->validated('metrics')));

        return $metrics;
    }

    /**
     * @return array{queries?: int, pages?: int, url_inspections?: int}
     */
    public function limits(): array
    {
        /** @var array{queries?: int, pages?: int, url_inspections?: int} $limits */
        $limits = $this->validated('limits') ?? [];

        return array_filter(
            [
                'queries' => isset($limits['queries']) ? (int) $limits['queries'] : null,
                'pages' => isset($limits['pages']) ? (int) $limits['pages'] : null,
                'url_inspections' => isset($limits['url_inspections']) ? (int) $limits['url_inspections'] : null,
            ],
            static fn (?int $value): bool => $value !== null,
        );
    }
}
