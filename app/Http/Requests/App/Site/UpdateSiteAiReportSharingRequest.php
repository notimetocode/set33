<?php

namespace App\Http\Requests\App\Site;

use App\Enums\AiReportVisibility;
use App\Models\SiteAiReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSiteAiReportSharingRequest extends FormRequest
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
            'visibility' => ['required', 'string', Rule::enum(AiReportVisibility::class)],
            'password' => ['nullable', 'string', 'min:6', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visibility.required' => 'Выберите режим доступа.',
            'visibility.enum' => 'Выбран недопустимый режим доступа.',
            'password.min' => 'Пароль должен содержать не менее :min символов.',
            'password.max' => 'Пароль слишком длинный.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $visibility = AiReportVisibility::tryFrom((string) $this->input('visibility'));

            if ($visibility !== AiReportVisibility::Password) {
                return;
            }

            /** @var SiteAiReport|null $report */
            $report = $this->route('ai_report');
            $hasExistingPassword = $report instanceof SiteAiReport
                && $report->visibility === AiReportVisibility::Password
                && filled($report->share_password);

            if (! $this->filled('password') && ! $hasExistingPassword) {
                $validator->errors()->add('password', 'Задайте пароль для доступа по ссылке.');
            }
        });
    }
}
