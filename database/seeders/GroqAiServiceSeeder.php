<?php

namespace Database\Seeders;

use App\Enums\AiServiceStatus;
use App\Enums\AiServiceType;
use App\Models\AiService;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class GroqAiServiceSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@example.com';

    /**
     * Глобальные Groq-сервисы для локальной разработки / демо.
     *
     * @var list<string>
     */
    private const MODELS = [
        'openai/gpt-oss-120b',
        'openai/gpt-oss-20b',
        'qwen/qwen3.8-27b',
    ];

    public function run(): void
    {
        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();

        if ($admin === null) {
            throw new RuntimeException(
                'Пользователь «'.self::ADMIN_EMAIL.'» не найден. Сначала запустите AdminUserSeeder.',
            );
        }

        $apiKey = config('services.groq.seed_api_key');

        if (! filled($apiKey)) {
            throw new RuntimeException(
                'Не задан GROQ_SEED_API_KEY. Добавьте ключ в .env для сида AI-сервиса.',
            );
        }

        $type = AiServiceType::Groq;

        AiService::query()
            ->where('is_global', false)
            ->where('type', $type)
            ->whereHas('user', fn ($query) => $query->where('email', 'user@example.com'))
            ->delete();

        $expectedNames = array_map(
            fn (string $model): string => $type->serviceName($model),
            self::MODELS,
        );

        AiService::query()
            ->where('is_global', true)
            ->where('type', $type)
            ->whereNotIn('name', $expectedNames)
            ->delete();

        foreach (self::MODELS as $model) {
            $this->seedGlobalGroq($admin, $type, $model, $apiKey);
        }
    }

    private function seedGlobalGroq(User $admin, AiServiceType $type, string $model, string $apiKey): void
    {
        $name = $type->serviceName($model);

        AiService::query()->updateOrCreate(
            [
                'is_global' => true,
                'type' => $type,
                'name' => $name,
            ],
            [
                'user_id' => $admin->id,
                'api_key' => $apiKey,
                'settings' => [
                    'model' => $model,
                    'system_instruction' => null,
                    'generation_config' => [
                        'temperature' => 1,
                        'top_p' => 0.95,
                        'max_output_tokens' => 8192,
                        'stop_sequences' => [],
                        'presence_penalty' => null,
                        'frequency_penalty' => null,
                    ],
                ],
                'status' => AiServiceStatus::Ok,
                'status_message' => 'Подключение к Groq работает.',
                'status_checked_at' => now(),
            ],
        );
    }
}
