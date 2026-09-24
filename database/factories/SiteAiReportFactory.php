<?php

namespace Database\Factories;

use App\Enums\AiServiceType;
use App\Models\AiService;
use App\Models\Site;
use App\Models\SiteAiReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteAiReport>
 */
class SiteAiReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = now()->subDays(28)->toDateString();
        $to = now()->subDay()->toDateString();
        $model = 'gemini-3.6-flash';

        return [
            'site_id' => Site::factory(),
            'ai_service_id' => AiService::factory(),
            'ai_service_name' => AiServiceType::Gemini->serviceName($model),
            'ai_service_type' => AiServiceType::Gemini->value,
            'model' => $model,
            'period_from' => $from,
            'period_to' => $to,
            'reply' => "## Краткое резюме\n\n".fake()->paragraph(),
            'usage' => [
                'prompt_tokens' => 120,
                'candidates_tokens' => 40,
                'total_tokens' => 160,
                'thoughts_tokens' => null,
            ],
            'data_counts' => [
                'analytics' => 7,
                'search_console' => 7,
                'search_console_queries' => 20,
                'search_console_pages' => 10,
                'search_console_devices' => 3,
                'search_console_countries' => 5,
                'github_commits' => 3,
                'events' => 1,
            ],
        ];
    }
}
