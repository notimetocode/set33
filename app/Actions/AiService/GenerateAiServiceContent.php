<?php

namespace App\Actions\AiService;

use App\Enums\AiServiceType;
use App\Models\AiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GenerateAiServiceContent
{
    /**
     * @return array{
     *     ok: bool,
     *     reply: string|null,
     *     message: string|null,
     *     model: string|null,
     *     usage: array{
     *         prompt_tokens: int|null,
     *         candidates_tokens: int|null,
     *         total_tokens: int|null,
     *         thoughts_tokens: int|null
     *     }|null
     * }
     */
    public function handle(AiService $aiService, string $prompt): array
    {
        if (! $aiService->hasApiKey()) {
            return $this->failure('API-ключ не задан.');
        }

        if ($aiService->type !== AiServiceType::Gemini) {
            return $this->failure('Генерация для этого типа сервиса пока не поддерживается.');
        }

        $model = (string) ($aiService->settings['model'] ?? '');

        if ($model === '') {
            return $this->failure('Не указана модель Gemini.');
        }

        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $endpoint = '/v1beta/models/'.rawurlencode($model).':generateContent';
        $payload = $this->buildPayload($aiService, $prompt);

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(['x-goog-api-key' => $aiService->api_key])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(60)
                ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false)
                ->post($endpoint, $payload);
        } catch (RequestException $exception) {
            return $this->failure(
                $this->extractApiError($exception->response?->json())
                    ?? 'Gemini API вернул ошибку. Попробуйте позже.',
            );
        } catch (ConnectionException) {
            return $this->failure(
                'Не удалось связаться с Gemini API. Проверьте соединение и попробуйте снова.',
            );
        }

        if (! $response->successful()) {
            return $this->failure(
                $this->extractApiError($response->json())
                    ?? 'Gemini API вернул ошибку. Попробуйте позже.',
            );
        }

        $json = $response->json();
        $reply = $this->extractReply($json);

        if ($reply === null || $reply === '') {
            return $this->failure('Модель вернула пустой ответ. Попробуйте другой промпт.');
        }

        return [
            'ok' => true,
            'reply' => $reply,
            'message' => null,
            'model' => $model,
            'usage' => $this->extractUsage($json),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(AiService $aiService, string $prompt): array
    {
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ];

        $systemInstruction = $aiService->settings['system_instruction'] ?? null;

        if (is_string($systemInstruction) && trim($systemInstruction) !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        $generationConfig = $this->mapGenerationConfig(
            is_array($aiService->settings['generation_config'] ?? null)
                ? $aiService->settings['generation_config']
                : [],
        );

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function mapGenerationConfig(array $config): array
    {
        $mapped = [];

        $scalarMap = [
            'temperature' => 'temperature',
            'top_p' => 'topP',
            'top_k' => 'topK',
            'max_output_tokens' => 'maxOutputTokens',
            'candidate_count' => 'candidateCount',
            'seed' => 'seed',
            'presence_penalty' => 'presencePenalty',
            'frequency_penalty' => 'frequencyPenalty',
            'response_mime_type' => 'responseMimeType',
        ];

        foreach ($scalarMap as $source => $target) {
            if (! array_key_exists($source, $config) || $config[$source] === null || $config[$source] === '') {
                continue;
            }

            $mapped[$target] = $config[$source];
        }

        if (isset($config['stop_sequences']) && is_array($config['stop_sequences']) && $config['stop_sequences'] !== []) {
            $mapped['stopSequences'] = array_values(array_filter(
                $config['stop_sequences'],
                fn (mixed $value): bool => is_string($value) && $value !== '',
            ));
        }

        $thinkingBudget = data_get($config, 'thinking_config.thinking_budget');

        if ($thinkingBudget !== null && $thinkingBudget !== '') {
            $mapped['thinkingConfig'] = [
                'thinkingBudget' => (int) $thinkingBudget,
            ];
        }

        return $mapped;
    }

    /**
     * @return array{
     *     ok: false,
     *     reply: null,
     *     message: string,
     *     model: null,
     *     usage: null
     * }
     */
    private function failure(string $message): array
    {
        return [
            'ok' => false,
            'reply' => null,
            'message' => $message,
            'model' => null,
            'usage' => null,
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

    /**
     * @return array{
     *     prompt_tokens: int|null,
     *     candidates_tokens: int|null,
     *     total_tokens: int|null,
     *     thoughts_tokens: int|null
     * }|null
     */
    private function extractUsage(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $meta = $payload['usageMetadata'] ?? null;

        if (! is_array($meta)) {
            return null;
        }

        $prompt = $meta['promptTokenCount'] ?? null;
        $candidates = $meta['candidatesTokenCount'] ?? null;
        $total = $meta['totalTokenCount'] ?? null;
        $thoughts = $meta['thoughtsTokenCount'] ?? null;

        if ($prompt === null && $candidates === null && $total === null && $thoughts === null) {
            return null;
        }

        return [
            'prompt_tokens' => is_numeric($prompt) ? (int) $prompt : null,
            'candidates_tokens' => is_numeric($candidates) ? (int) $candidates : null,
            'total_tokens' => is_numeric($total) ? (int) $total : null,
            'thoughts_tokens' => is_numeric($thoughts) ? (int) $thoughts : null,
        ];
    }

    private function extractReply(mixed $payload): ?string
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

        return trim(implode("\n", $chunks));
    }
}
