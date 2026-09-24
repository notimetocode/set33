<?php

namespace Database\Factories;

use App\Enums\AiServiceStatus;
use App\Enums\AiServiceType;
use App\Models\AiService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiService>
 */
class AiServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'type' => AiServiceType::Gemini,
            'api_key' => 'test-gemini-api-key-'.fake()->uuid(),
            'settings' => [
                'model' => 'gemini-3.6-flash',
                'system_instruction' => null,
                'generation_config' => [
                    'temperature' => 1.0,
                    'top_p' => 0.95,
                    'top_k' => 40,
                    'max_output_tokens' => 8192,
                    'candidate_count' => 1,
                    'stop_sequences' => [],
                    'seed' => null,
                    'presence_penalty' => null,
                    'frequency_penalty' => null,
                    'response_mime_type' => 'text/plain',
                    'thinking_config' => [
                        'thinking_budget' => -1,
                    ],
                ],
            ],
            'status' => AiServiceStatus::Unchecked,
            'status_message' => null,
            'status_checked_at' => null,
        ];
    }
}
