<?php

namespace App\Actions\Site;

use App\Models\AiService;
use App\Models\Site;
use App\Models\SiteAiReport;
use App\Support\Localization;

class SaveSiteAiReport
{
    /**
     * @param  array{
     *     reply: string,
     *     charts?: list<array{
     *         id: string,
     *         type: string,
     *         title: string,
     *         labels: list<string>,
     *         series: list<array{name: string, values: list<float|int>}>
     *     }>,
     *     model: string|null,
     *     usage: array{
     *         prompt_tokens: int|null,
     *         candidates_tokens: int|null,
     *         total_tokens: int|null,
     *         thoughts_tokens: int|null
     *     }|null,
     *     period: array{from: string, to: string},
     *     data_counts: array{
     *         analytics: int,
     *         search_console: int,
     *         search_console_queries?: int,
     *         search_console_pages?: int,
     *         search_console_devices?: int,
     *         search_console_countries?: int,
     *         search_console_appearances?: int,
     *         search_console_sitemaps?: int,
     *         search_console_url_inspections?: int,
     *         pagespeed_lab?: int,
     *         pagespeed_crux?: int,
     *         github_commits: int,
     *         events: int,
     *         documents?: int
     *     },
     *     use_system_prompt?: bool,
     *     prompt?: string|null,
     *     locale?: string|null
     * }  $result
     */
    public function handle(Site $site, AiService $aiService, array $result): SiteAiReport
    {
        $useSystemPrompt = (bool) ($result['use_system_prompt'] ?? true);

        return SiteAiReport::query()->create([
            'site_id' => $site->id,
            'ai_service_id' => $aiService->id,
            'ai_service_name' => $aiService->name,
            'ai_service_type' => $aiService->type->value,
            'model' => $result['model'] ?? ($aiService->settings['model'] ?? null),
            'period_from' => $result['period']['from'],
            'period_to' => $result['period']['to'],
            'use_system_prompt' => $useSystemPrompt,
            'prompt' => $useSystemPrompt ? null : ($result['prompt'] ?? null),
            'reply' => $result['reply'],
            'charts' => $result['charts'] ?? [],
            'usage' => $result['usage'],
            'data_counts' => $result['data_counts'],
            'locale' => $this->resolveLocale($result['locale'] ?? null),
        ]);
    }

    private function resolveLocale(?string $locale): string
    {
        if (is_string($locale) && Localization::isSupported($locale)) {
            return $locale;
        }

        $appLocale = app()->getLocale();

        if (Localization::isSupported($appLocale)) {
            return $appLocale;
        }

        return Localization::defaultLocale();
    }
}
