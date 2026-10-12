<?php

namespace App\Http\Requests\App\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSiteAiReportCommitsRequest extends FormRequest
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
        $site = $this->route('site');

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
            'commit_ids' => ['required', 'array', 'min:1', 'max:50'],
            'commit_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('site_github_commits', 'id')->where(
                    fn ($query) => $query
                        ->where('site_id', $site->id)
                        ->whereNotNull('files_fetched_at'),
                ),
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
            'commit_ids.required' => 'Укажите коммиты для ревью.',
            'commit_ids.min' => 'Укажите хотя бы один коммит для ревью.',
            'commit_ids.*.exists' => 'Коммит недоступен или у него ещё нет diffs.',
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
