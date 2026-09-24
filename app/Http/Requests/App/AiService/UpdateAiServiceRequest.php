<?php

namespace App\Http\Requests\App\AiService;

use App\Enums\AiServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiServiceRequest extends FormRequest
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
        return array_merge([
            'type' => ['required', 'string', Rule::enum(AiServiceType::class)],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'settings' => ['required', 'array'],
        ], $this->settingsRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsRules(): array
    {
        if ($this->input('type') !== AiServiceType::Gemini->value) {
            return [];
        }

        return GeminiSettingsRules::rules();
    }
}
