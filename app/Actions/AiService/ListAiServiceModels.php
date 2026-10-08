<?php

namespace App\Actions\AiService;

use App\Enums\AiServiceType;

class ListAiServiceModels
{
    public function __construct(
        private ListGeminiModels $listGemini,
        private ListGroqModels $listGroq,
    ) {}

    /**
     * @return list<array{id: string, name: string}>
     */
    public function handle(AiServiceType $type, string $apiKey): array
    {
        return match ($type) {
            AiServiceType::Gemini => $this->listGemini->handle($apiKey),
            AiServiceType::Groq => $this->listGroq->handle($apiKey),
        };
    }
}
