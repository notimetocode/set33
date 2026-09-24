<?php

namespace App\Http\Requests\App\AiService;

final class GeminiSettingsRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'settings.model' => ['required', 'string', 'max:255'],
            'settings.system_instruction' => ['nullable', 'string', 'max:10000'],
            'settings.generation_config' => ['required', 'array'],
            'settings.generation_config.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'settings.generation_config.top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'settings.generation_config.top_k' => ['nullable', 'integer', 'min:1'],
            'settings.generation_config.max_output_tokens' => ['nullable', 'integer', 'min:1'],
            'settings.generation_config.candidate_count' => ['nullable', 'integer', 'min:1', 'max:8'],
            'settings.generation_config.stop_sequences' => ['nullable', 'array'],
            'settings.generation_config.stop_sequences.*' => ['string', 'max:255'],
            'settings.generation_config.seed' => ['nullable', 'integer'],
            'settings.generation_config.presence_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'settings.generation_config.frequency_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'settings.generation_config.response_mime_type' => ['nullable', 'string', 'max:255'],
            'settings.generation_config.thinking_config' => ['nullable', 'array'],
            'settings.generation_config.thinking_config.thinking_budget' => ['nullable', 'integer', 'min:-1'],
        ];
    }
}
