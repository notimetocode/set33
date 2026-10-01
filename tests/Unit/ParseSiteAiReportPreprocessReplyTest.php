<?php

namespace Tests\Unit;

use App\Actions\Site\ParseSiteAiReportPreprocessReply;
use PHPUnit\Framework\TestCase;

class ParseSiteAiReportPreprocessReplyTest extends TestCase
{
    public function test_parses_all_known_types_and_drops_unknown(): void
    {
        $parser = new ParseSiteAiReportPreprocessReply;
        $reply = json_encode([
            'items' => [
                [
                    'type' => 'github_commit_files',
                    'commit_id' => 1,
                    'sha' => 'abcdef1234567890abcdef1234567890abcdef12',
                    'reason' => 'Deploy',
                ],
                [
                    'type' => 'gsc_url_inspection',
                    'url' => 'https://example.com/page',
                    'reason' => 'Indexing',
                ],
                [
                    'type' => 'gsc_dimensions',
                    'reason' => 'Missing dims',
                ],
                [
                    'type' => 'pagespeed_lab_details',
                    'snapshot_id' => 9,
                    'reason' => 'Details',
                ],
                [
                    'type' => 'future_unknown_type',
                    'reason' => 'Skip me',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $parser->handle($reply);

        $this->assertTrue($result['ok']);
        $this->assertCount(4, $result['items']);
        $this->assertSame('github_commit_files', $result['items'][0]['type']);
        $this->assertSame('gsc_url_inspection', $result['items'][1]['type']);
        $this->assertSame('https://example.com/page', $result['items'][1]['url']);
        $this->assertSame('gsc_dimensions', $result['items'][2]['type']);
        $this->assertSame(9, $result['items'][3]['snapshot_id']);
    }

    public function test_rejects_invalid_json(): void
    {
        $parser = new ParseSiteAiReportPreprocessReply;
        $result = $parser->handle('not json');

        $this->assertFalse($result['ok']);
        $this->assertSame([], $result['items']);
    }
}
