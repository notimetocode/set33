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
     *     retryable: bool,
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

        return match ($aiService->type) {
            AiServiceType::Gemini => $this->generateGemini($aiService, $prompt),
            AiServiceType::Groq => $this->generateGroq($aiService, $prompt),
        };
    }

    /**
     * @return array{
     *     ok: bool,
     *     reply: string|null,
     *     message: string|null,
     *     retryable: bool,
     *     model: string|null,
     *     usage: array{
     *         prompt_tokens: int|null,
     *         candidates_tokens: int|null,
     *         total_tokens: int|null,
     *         thoughts_tokens: int|null
     *     }|null
     * }
     */
    private function generateGemini(AiService $aiService, string $prompt): array
    {
        $model = (string) ($aiService->settings['model'] ?? '');

        if ($model === '') {
            return $this->failure('Не указана модель Gemini.');
        }

        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $endpoint = '/v1beta/models/'.rawurlencode($model).':generateContent';
        $payload = $this->buildGeminiPayload($aiService, $prompt);

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(['x-goog-api-key' => $aiService->api_key])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(60)
                ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false)
                ->post($endpoint, $payload);
        } catch (RequestException $exception) {
            $json = $exception->response?->json();
            $message = $this->extractApiError($json)
                ?? 'Gemini API вернул ошибку. Попробуйте позже.';

            return $this->failure(
                $message,
                $this->isHighDemandError($json, $exception->response?->status()),
            );
        } catch (ConnectionException) {
            return $this->failure(
                'Не удалось связаться с Gemini API. Проверьте соединение и попробуйте снова.',
                true,
            );
        }

        if (! $response->successful()) {
            $json = $response->json();
            $message = $this->extractApiError($json)
                ?? 'Gemini API вернул ошибку. Попробуйте позже.';

            return $this->failure(
                $message,
                $this->isHighDemandError($json, $response->status()),
            );
        }

        $json = $response->json();
        $reply = $this->extractGeminiReply($json);

        if ($reply === null || $reply === '') {
            return $this->failure('Модель вернула пустой ответ. Попробуйте другой промпт.');
        }

        return [
            'ok' => true,
            'reply' => $reply,
            'message' => null,
            'retryable' => false,
            'model' => $model,
            'usage' => $this->extractGeminiUsage($json),
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     reply: string|null,
     *     message: string|null,
     *     retryable: bool,
     *     model: string|null,
     *     usage: array{
     *         prompt_tokens: int|null,
     *         candidates_tokens: int|null,
     *         total_tokens: int|null,
     *         thoughts_tokens: int|null
     *     }|null
     * }
     */
    private function generateGroq(AiService $aiService, string $prompt): array
    {
        $model = (string) ($aiService->settings['model'] ?? '');

        if ($model === '') {
            return $this->failure('Не указана модель Groq.');
        }

        $baseUrl = rtrim((string) config('services.groq.base_url'), '/');
        $payload = $this->buildGroqPayload($aiService, $prompt);

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken((string) $aiService->api_key)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(60)
                ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, false)
                ->post('/chat/completions', $payload);
        } catch (RequestException $exception) {
            $json = $exception->response?->json();
            $message = $this->extractApiError($json)
                ?? 'Groq API вернул ошибку. Попробуйте позже.';

            return $this->failure(
                $message,
                $this->isHighDemandError($json, $exception->response?->status()),
            );
        } catch (ConnectionException) {
            return $this->failure(
                'Не удалось связаться с Groq API. Проверьте соединение и попробуйте снова.',
                true,
            );
        }

        if (! $response->successful()) {
            $json = $response->json();
            $message = $this->extractApiError($json)
                ?? 'Groq API вернул ошибку. Попробуйте позже.';

            return $this->failure(
                $message,
                $this->isHighDemandError($json, $response->status()),
            );
        }

        $json = $response->json();
        $reply = $this->extractGroqReply($json);

        if ($reply === null || $reply === '') {
            return $this->failure('Модель вернула пустой ответ. Попробуйте другой промпт.');
        }

        return [
            'ok' => true,
            'reply' => $reply,
            'message' => null,
            'retryable' => false,
            'model' => $model,
            'usage' => $this->extractGroqUsage($json),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGeminiPayload(AiService $aiService, string $prompt): array
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

        $generationConfig = $this->mapGeminiGenerationConfig(
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
     * @return array<string, mixed>
     */
    private function buildGroqPayload(AiService $aiService, string $prompt): array
    {
        $messages = [];
        $systemInstruction = $aiService->settings['system_instruction'] ?? null;

        if (is_string($systemInstruction) && trim($systemInstruction) !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $systemInstruction,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $payload = [
            'model' => (string) ($aiService->settings['model'] ?? ''),
            'messages' => $messages,
        ];

        $config = is_array($aiService->settings['generation_config'] ?? null)
            ? $aiService->settings['generation_config']
            : [];

        foreach ([
            'temperature' => 'temperature',
            'top_p' => 'top_p',
            'presence_penalty' => 'presence_penalty',
            'frequency_penalty' => 'frequency_penalty',
        ] as $source => $target) {
            if (! array_key_exists($source, $config) || $config[$source] === null || $config[$source] === '') {
                continue;
            }

            $payload[$target] = $config[$source];
        }

        if (isset($config['max_output_tokens']) && $config['max_output_tokens'] !== null && $config['max_output_tokens'] !== '') {
            $payload['max_tokens'] = (int) $config['max_output_tokens'];
        }

        if (isset($config['stop_sequences']) && is_array($config['stop_sequences']) && $config['stop_sequences'] !== []) {
            $stops = array_values(array_filter(
                $config['stop_sequences'],
                fn (mixed $value): bool => is_string($value) && $value !== '',
            ));

            if ($stops !== []) {
                $payload['stop'] = $stops;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function mapGeminiGenerationConfig(array $config): array
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
     *     retryable: bool,
     *     model: null,
     *     usage: null
     * }
     */
    private function failure(string $message, bool $retryable = false): array
    {
        return [
            'ok' => false,
            'reply' => null,
            'message' => $message,
            'retryable' => $retryable,
            'model' => null,
            'usage' => null,
        ];
    }

    private function isHighDemandError(mixed $payload, ?int $status = null): bool
    {
        if (in_array($status, [429, 503], true)) {
            return true;
        }

        $apiStatus = strtoupper((string) data_get(is_array($payload) ? $payload : [], 'error.status', ''));

        if (in_array($apiStatus, ['RESOURCE_EXHAUSTED', 'UNAVAILABLE'], true)) {
            return true;
        }

        $message = strtolower((string) ($this->extractApiError($payload) ?? ''));

        return str_contains($message, 'high demand')
            || str_contains($message, 'resource exhausted')
            || str_contains($message, 'resource_exhausted')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests');
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
    private function extractGeminiUsage(mixed $payload): ?array
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

    /**
     * @return array{
     *     prompt_tokens: int|null,
     *     candidates_tokens: int|null,
     *     total_tokens: int|null,
     *     thoughts_tokens: int|null
     * }|null
     */
    private function extractGroqUsage(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $usage = $payload['usage'] ?? null;

        if (! is_array($usage)) {
            return null;
        }

        $prompt = $usage['prompt_tokens'] ?? null;
        $candidates = $usage['completion_tokens'] ?? null;
        $total = $usage['total_tokens'] ?? null;

        if ($prompt === null && $candidates === null && $total === null) {
            return null;
        }

        return [
            'prompt_tokens' => is_numeric($prompt) ? (int) $prompt : null,
            'candidates_tokens' => is_numeric($candidates) ? (int) $candidates : null,
            'total_tokens' => is_numeric($total) ? (int) $total : null,
            'thoughts_tokens' => null,
        ];
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

        return trim(implode("\n", $chunks));
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

        return trim($content);
    }
}
