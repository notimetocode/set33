<?php

namespace App\Actions\AiService;

use App\Enums\AiServiceStatus;
use App\Enums\AiServiceType;
use App\Models\AiService;
use App\Models\User;

class CreateAiService
{
    /**
     * @param  array{type: string, api_key: string, settings: array<string, mixed>}  $data
     */
    public function handle(User $user, array $data): AiService
    {
        $type = AiServiceType::from($data['type']);

        return $user->aiServices()->create([
            'name' => $type->serviceName($data['settings']['model'] ?? null),
            'type' => $type,
            'api_key' => $data['api_key'],
            'settings' => $data['settings'],
            'status' => AiServiceStatus::Unchecked,
        ]);
    }
}
