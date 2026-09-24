<?php

namespace App\Actions\AiService;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListGeminiModels
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public function handle(string $apiKey): array
    {
        $models = [];
        $pageToken = null;
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');

        try {
            do {
                $response = Http::baseUrl($baseUrl)
                    ->withHeaders(['x-goog-api-key' => $apiKey])
                    ->acceptJson()
                    ->connectTimeout(3)
                    ->timeout(15)
                    ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false)
                    ->get('/v1beta/models', array_filter([
                        'pageSize' => 100,
                        'pageToken' => $pageToken,
                    ], fn (mixed $value): bool => filled($value)));

                if ($response->unauthorized() || $response->forbidden() || $response->status() === 400) {
                    throw ValidationException::withMessages([
                        'api_key' => 'Неверный API-ключ или нет доступа к Gemini API.',
                    ]);
                }

                if (! $response->successful()) {
                    throw ValidationException::withMessages([
                        'api_key' => 'Не удалось получить список моделей Gemini. Попробуйте позже.',
                    ]);
                }

                /** @var list<array<string, mixed>> $pageModels */
                $pageModels = $response->json('models') ?? [];

                foreach ($pageModels as $model) {
                    $methods = $model['supportedGenerationMethods'] ?? [];

                    if (! is_array($methods) || ! in_array('generateContent', $methods, true)) {
                        continue;
                    }

                    $name = (string) ($model['name'] ?? '');
                    $id = Str::after($name, 'models/');

                    if ($id === '' || $id === $name) {
                        continue;
                    }

                    $models[] = [
                        'id' => $id,
                        'name' => (string) ($model['displayName'] ?? $id),
                    ];
                }

                $pageToken = $response->json('nextPageToken');
            } while (filled($pageToken));
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'api_key' => 'Не удалось связаться с Gemini API. Проверьте соединение и попробуйте снова.',
            ]);
        }

        usort(
            $models,
            fn (array $left, array $right): int => strcmp($left['id'], $right['id']),
        );

        return array_values($models);
    }
}
