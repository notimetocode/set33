<?php

namespace App\Http\Requests\App\PageSpeed;

use App\Support\SiteSyncMetrics;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncSitePageSpeedIntegrationRequest extends FormRequest
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
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*' => ['required', 'string', Rule::in(SiteSyncMetrics::PAGESPEED)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'metrics.required' => 'Выберите хотя бы одну метрику для загрузки.',
            'metrics.min' => 'Выберите хотя бы одну метрику для загрузки.',
            'metrics.*.in' => 'Выбрана неизвестная метрика.',
        ];
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
}
