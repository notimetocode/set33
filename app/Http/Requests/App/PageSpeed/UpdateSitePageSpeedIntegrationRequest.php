<?php

namespace App\Http\Requests\App\PageSpeed;

use App\Enums\PageSpeedStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSitePageSpeedIntegrationRequest extends FormRequest
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
            'strategy' => ['required', 'string', Rule::enum(PageSpeedStrategy::class)],
            'page_urls' => ['nullable', 'array', 'max:10'],
            'page_urls.*' => ['required', 'string', 'max:768'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'strategy.required' => 'Выберите стратегию измерения.',
            'strategy.Illuminate\Validation\Rules\Enum' => 'Некорректная стратегия измерения.',
            'page_urls.max' => 'Можно указать не больше 10 URL страниц.',
            'page_urls.*.required' => 'Укажите URL страницы.',
            'page_urls.*.max' => 'URL страницы слишком длинный.',
        ];
    }
}
