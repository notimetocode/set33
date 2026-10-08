<?php

namespace App\Http\Requests\Admin\AiService;

use App\Enums\AiServiceType;
use App\Http\Requests\App\AiService\GeminiSettingsRules;
use App\Http\Requests\App\AiService\GroqSettingsRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiServiceRequest extends FormRequest
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
            'api_key' => ['required', 'string', 'max:2048'],
            'settings' => ['required', 'array'],
        ], $this->settingsRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsRules(): array
    {
        return match ($this->input('type')) {
            AiServiceType::Gemini->value => GeminiSettingsRules::rules(),
            AiServiceType::Groq->value => GroqSettingsRules::rules(),
            default => [],
        };
    }
}
