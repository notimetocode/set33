<?php

namespace Tests\Unit;

use App\Actions\Site\BuildSiteAiReportPreprocessPrompt;
use App\Models\Site;
use PHPUnit\Framework\TestCase;

class BuildSiteAiReportPreprocessPromptTest extends TestCase
{
    public function test_prompt_prioritizes_search_console_change_points_and_nearby_commits(): void
    {
        $site = new Site;
        $site->forceFill([
            'name' => 'Demo',
            'url' => 'https://example.com',
            'domain' => 'example.com',
        ]);
        $site->id = 1;

        $prompt = (new BuildSiteAiReportPreprocessPrompt)->handle(
            $site,
            '2026-09-01',
            '2026-09-07',
            [],
            [
                [
                    'date' => '2026-09-03',
                    'clicks' => 10,
                    'impressions' => 200,
                    'ctr' => 0.05,
                    'position' => 8.0,
                ],
            ],
            [
                [
                    'id' => 42,
                    'sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'short_sha' => 'aaaaaaa',
                    'message' => 'SEO change',
                    'author_name' => 'Dev',
                    'author_date' => '2026-09-02T10:00:00+00:00',
                    'has_files' => false,
                    'files_incomplete' => false,
                ],
            ],
        );

        $this->assertStringContainsString('Search Console (daily', $prompt);
        $this->assertStringContainsString('2026-09-03', $prompt);
        $this->assertStringContainsString('сильно изменился', $prompt);
        $this->assertStringContainsString('−3…+1', $prompt);
        $this->assertStringContainsString('github_commit_files', $prompt);
        $this->assertStringContainsString('приоритет github_commit_files', $prompt);
        $this->assertStringContainsString('не выдумывай аномалии', $prompt);
    }
}
