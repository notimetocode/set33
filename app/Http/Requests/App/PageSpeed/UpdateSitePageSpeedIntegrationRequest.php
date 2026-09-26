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
        ];
    }
}
