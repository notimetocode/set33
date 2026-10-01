<?php

namespace App\Http\Requests\App\Site;

use App\Actions\Site\ParseSiteAiReportPreprocessReply;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplySiteAiReportPreprocessRequest extends FormRequest
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
            'to' => ['required', 'date', 'after_or_equal:from'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.key' => ['required', 'string', 'max:900'],
            'items.*.type' => ['required', 'string', Rule::in(ParseSiteAiReportPreprocessReply::ALLOWED_TYPES)],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
            'items.*.commit_id' => ['nullable', 'integer', 'min:1'],
            'items.*.sha' => ['nullable', 'string', 'max:40'],
            'items.*.url' => ['nullable', 'string', 'max:768'],
            'items.*.snapshot_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Выберите хотя бы один пункт для довыгрузки.',
            'items.*.type.in' => 'Неизвестный тип довыгрузки.',
        ];
    }
}
