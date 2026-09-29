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
    private const ADMIN_EMAIL = 'admin@example.com';

    /**
     * Актуальная модель для глобального Gemini (рекомендация Google для новых ключей).
     */
    private const MODEL = 'gemini-3.6-flash';

    public function run(): void
    {
        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();

        if ($admin === null) {
            throw new RuntimeException(
                'Пользователь «'.self::ADMIN_EMAIL.'» не найден. Сначала запустите AdminUserSeeder.',
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

        AiService::query()
            ->where('is_global', false)
            ->where('type', $type)
            ->whereHas('user', fn ($query) => $query->where('email', 'user@example.com'))
            ->delete();

        AiService::query()->updateOrCreate(
            [
                'is_global' => true,
                'type' => $type,
            ],
            [
                'user_id' => $admin->id,
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
