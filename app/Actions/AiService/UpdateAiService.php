<?php

namespace App\Actions\AiService;

use App\Enums\AiServiceType;
use App\Models\AiService;

class UpdateAiService
{
    /**
     * @param  array{type: string, api_key?: string|null, settings: array<string, mixed>}  $data
     */
    public function handle(AiService $aiService, array $data): AiService
    {
        $type = AiServiceType::from($data['type']);

        $payload = [
            'name' => $type->serviceName($data['settings']['model'] ?? null),
            'type' => $type,
            'settings' => $data['settings'],
        ];

        if (array_key_exists('api_key', $data) && filled($data['api_key'])) {
            $payload['api_key'] = $data['api_key'];
        }

        $keyChanged = array_key_exists('api_key', $payload);
        $modelChanged = ($aiService->settings['model'] ?? null) !== ($data['settings']['model'] ?? null);

        $aiService->update($payload);

        if ($keyChanged || $modelChanged) {
            $aiService->markStatusUnchecked();
        }

        return $aiService->refresh();
    }
}
