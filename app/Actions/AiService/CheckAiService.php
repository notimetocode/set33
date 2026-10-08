<?php

namespace App\Actions\AiService;

use App\Enums\AiServiceStatus;
use App\Enums\AiServiceType;
use App\Models\AiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CheckAiService
{
    private const PROBE_PROMPT = 'Ответь одним словом: OK';

    /**
     * @return array{
     *     ok: bool,
     *     title: string,
     *     message: string,
     *     reply: string|null,
     *     service: AiService
     * }
     */
    public function handle(AiService $aiService): array
    {
        if (! $aiService->hasApiKey()) {
            return $this->persist($aiService, AiServiceStatus::Error, 'API-ключ не задан.', null);
        }

        return match ($aiService->type) {
            AiServiceType::Gemini => $this->checkGemini($aiService),
            AiServiceType::Groq => $this->checkGroq($aiService),
        };
    }

    /**
     * @return array{
     *     ok: bool,
     *     title: string,
     *     message: string,
     *     reply: string|null,
     *     service: AiService
     * }
     */
    private function checkGemini(AiService $aiService): array
    {
        $model = (string) ($aiService->settings['model'] ?? '');

        if ($model === '') {
            return $this->persist($aiService, AiServiceStatus::Error, 'Не указана модель Gemini.', null);
        }

        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $endpoint = '/v1beta/models/'.rawurlencode($model).':generateContent';

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(['x-goog-api-key' => $aiService->api_key])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(20)
                ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false)
                ->post($endpoint, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => self::PROBE_PROMPT],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0,
                        'maxOutputTokens' => 16,
                    ],
                ]);
        } catch (RequestException $exception) {
            $apiMessage = $this->extractApiError($exception->response?->json());

            return $this->persist(
                $aiService,
                AiServiceStatus::Error,
                $apiMessage ?? 'Gemini API вернул ошибку. Попробуйте позже.',
                null,
            );
        } catch (ConnectionException) {
            return $this->persist(
                $aiService,
                AiServiceStatus::Error,
                'Не удалось связаться с Gemini API. Проверьте соединение и попробуйте снова.',
                null,
            );
        }

        if (! $response->successful()) {
            $apiMessage = $this->extractApiError($response->json());

            return $this->persist(
                $aiService,
                AiServiceStatus::Error,
                $apiMessage ?? 'Gemini API вернул ошибку. Попробуйте позже.',
                null,
            );
        }

        $reply = $this->extractGeminiReply($response->json());

        if ($reply === null || $reply === '') {
            return $this->persist(
                $aiService,
                AiServiceStatus::Error,
                'Ключ принят, но ответ модели пуст. Проверьте настройки модели.',
                null,
            );
        }

        return $this->persist(
            $aiService,
            AiServiceStatus::Ok,
            'Подключение к Gemini работает.',
            $reply,
        );
    }

    /**
     * @return array{
     *     ok: bool,
     *     title: string,
     *     message: string,
     *     reply: string|null,
     *     service: AiService
     * }
     */
    private function checkGroq(AiService $aiService): array
    {
        $model = (string) ($aiService->settings['model'] ?? '');

        if ($model === '') {
            return $this->persist($aiService, AiServiceStatus::Error, 'Не указана модель Groq.', null);
        }

        $baseUrl = rtrim((string) config('services.groq.base_url'), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken((string) $aiService->api_key)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(20)
                ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false)
                ->post('/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => self::PROBE_PROMPT,
                        ],
                    ],
                    'temperature' => 0,
                    // Reasoning models (e.g. gpt-oss) spend tokens on "reasoning" before content.
                    'max_tokens' => 128,
                ]);
        } catch (RequestException $exception) {
            $apiMessage = $this->extractApiError($exception->response?->json());

            return $this->persist(
                $aiService,
                AiServiceStatus::Error,
                $apiMessage ?? 'Groq API вернул ошибку. Попробуйте позже.',
                null,
            );
        } catch (ConnectionException) {
            return $this->persist(
                $aiService,
                AiServiceStatus::Error,
                'Не удалось связаться с Groq API. Проверьте соединение и попробуйте снова.',
                null,
            );
        }

        if (! $response->successful()) {
            $apiMessage = $this->extractApiError($response->json());

            return $this->persist(
                $aiService,
                AiServiceStatus::Error,
                $apiMessage ?? 'Groq API вернул ошибку. Попробуйте позже.',
                null,
            );
        }

        $reply = $this->extractGroqReply($response->json());

        if ($reply === null || $reply === '') {
            return $this->persist(
                $aiService,
                AiServiceStatus::Error,
                'Ключ принят, но ответ модели пуст. Проверьте настройки модели.',
                null,
            );
        }

        return $this->persist(
            $aiService,
            AiServiceStatus::Ok,
            'Подключение к Groq работает.',
            $reply,
        );
    }

    /**
     * @return array{
     *     ok: bool,
     *     title: string,
     *     message: string,
     *     reply: string|null,
     *     service: AiService
     * }
     */
    private function persist(
        AiService $aiService,
        AiServiceStatus $status,
        string $message,
        ?string $reply,
    ): array {
        $aiService->forceFill([
            'status' => $status,
            'status_message' => $message,
            'status_checked_at' => now(),
        ])->save();

        $aiService->refresh();

        return [
            'ok' => $status === AiServiceStatus::Ok,
            'title' => $status === AiServiceStatus::Ok ? 'Проверка успешна' : 'Проверка не пройдена',
            'message' => $message,
            'reply' => $reply,
            'service' => $aiService,
        ];
    }

    private function extractApiError(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $message = data_get($payload, 'error.message');

        if (! is_string($message) || $message === '') {
            return null;
        }

        return trim($message);
    }

    private function extractGeminiReply(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $parts = data_get($payload, 'candidates.0.content.parts');

        if (! is_array($parts)) {
            return null;
        }

        $chunks = [];

        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text']) && $part['text'] !== '') {
                $chunks[] = $part['text'];
            }
        }

        if ($chunks === []) {
            return null;
        }

        return Str::limit(trim(implode(' ', $chunks)), 280);
    }

    private function extractGroqReply(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $content = data_get($payload, 'choices.0.message.content');

        if (! is_string($content) || $content === '') {
            return null;
        }

        return Str::limit(trim($content), 280);
    }
}
