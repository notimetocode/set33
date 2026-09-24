<?php

namespace Database\Seeders;

use App\Enums\AiServiceStatus;
use App\Enums\AiServiceType;
use App\Models\AiService;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AiServiceSeeder extends Seeder
{
    private const USER_EMAIL = 'user@example.com';

    /**
     * Актуальная модель для user@example.com (рекомендация Google для новых ключей).
     */
    private const MODEL = 'gemini-3.6-flash';

    public function run(): void
    {
        $user = User::query()->where('email', self::USER_EMAIL)->first();

        if ($user === null) {
            throw new RuntimeException(
                'Пользователь «'.self::USER_EMAIL.'» не найден. Сначала запустите AppUserSeeder.',
            );
        }

        $apiKey = config('services.gemini.seed_api_key');

        if (! filled($apiKey)) {
            throw new RuntimeException(
                'Не задан GEMINI_SEED_API_KEY. Добавьте ключ в .env для сида AI-сервиса.',
            );
        }

        $type = AiServiceType::Gemini;
        $model = self::MODEL;

        AiService::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
            ],
            [
                'name' => $type->serviceName($model),
                'api_key' => $apiKey,
                'settings' => [
                    'model' => $model,
                    'system_instruction' => null,
                    'generation_config' => [
                        'temperature' => 1,
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
                            'thinking_budget' => 0,
                        ],
                    ],
                ],
                'status' => AiServiceStatus::Ok,
                'status_message' => 'Подключение к Gemini работает.',
                'status_checked_at' => now(),
            ],
        );
    }
}
