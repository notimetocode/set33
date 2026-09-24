<?php

namespace App\Actions\Site;

use App\Models\AiService;
use App\Models\Site;
use App\Models\SiteAiReport;

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
     *         github_commits: int,
     *         events: int
     *     }
     * }  $result
     */
    public function handle(Site $site, AiService $aiService, array $result): SiteAiReport
    {
        return SiteAiReport::query()->create([
            'site_id' => $site->id,
            'ai_service_id' => $aiService->id,
            'ai_service_name' => $aiService->name,
            'ai_service_type' => $aiService->type->value,
            'model' => $result['model'] ?? ($aiService->settings['model'] ?? null),
            'period_from' => $result['period']['from'],
            'period_to' => $result['period']['to'],
            'reply' => $result['reply'],
            'charts' => $result['charts'] ?? [],
            'usage' => $result['usage'],
            'data_counts' => $result['data_counts'],
        ]);
    }
}
