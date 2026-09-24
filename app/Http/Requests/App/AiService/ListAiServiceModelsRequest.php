<?php

namespace App\Http\Requests\App\AiService;

use App\Enums\AiServiceType;
use App\Models\AiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListAiServiceModelsRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::enum(AiServiceType::class)],
            'api_key' => ['nullable', 'string', 'max:2048', 'required_without:ai_service_id'],
            'ai_service_id' => [
                'nullable',
                'integer',
                'required_without:api_key',
                Rule::exists('ai_services', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'api_key.required_without' => 'Укажите API-ключ или выберите сохранённый сервис.',
            'ai_service_id.required_without' => 'Укажите API-ключ или выберите сохранённый сервис.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('type') !== AiServiceType::Gemini->value) {
                $validator->errors()->add('type', 'Список моделей доступен только для Google Gemini.');
            }

            $serviceId = $this->integer('ai_service_id');

            if ($serviceId > 0 && ! filled($this->input('api_key'))) {
                $service = AiService::query()->find($serviceId);

                if ($service === null || $this->user()?->cannot('app.ai-services.view', $service)) {
                    $validator->errors()->add('ai_service_id', 'AI-сервис не найден или недоступен.');
                }
            }
        });
    }
}
