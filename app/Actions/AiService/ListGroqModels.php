<?php

namespace App\Actions\AiService;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ListGroqModels
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public function handle(string $apiKey): array
    {
        $baseUrl = rtrim((string) config('services.groq.base_url'), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(15)
                ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false)
                ->get('/models');
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'api_key' => 'Не удалось связаться с Groq API. Проверьте соединение и попробуйте снова.',
            ]);
        }

        if ($response->unauthorized() || $response->forbidden()) {
            throw ValidationException::withMessages([
                'api_key' => 'Неверный API-ключ или нет доступа к Groq API.',
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'api_key' => 'Не удалось получить список моделей Groq. Попробуйте позже.',
            ]);
        }

        /** @var list<array<string, mixed>> $pageModels */
        $pageModels = $response->json('data') ?? [];
        $models = [];

        foreach ($pageModels as $model) {
            $id = (string) ($model['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $models[] = [
                'id' => $id,
                'name' => $id,
            ];
        }

        usort(
            $models,
            fn (array $left, array $right): int => strcmp($left['id'], $right['id']),
        );

        return array_values($models);
    }
}
