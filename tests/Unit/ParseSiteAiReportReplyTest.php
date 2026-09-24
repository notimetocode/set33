<?php

namespace Tests\Unit;

use App\Actions\Site\ParseSiteAiReportReply;
use PHPUnit\Framework\TestCase;

class ParseSiteAiReportReplyTest extends TestCase
{
    private ParseSiteAiReportReply $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ParseSiteAiReportReply;
    }

    public function test_extracts_valid_charts_and_strips_block(): void
    {
        $raw = <<<'MD'
## Краткое резюме

Рост органики.

{{chart:organic_trend}}

```charts-json
[{"id":"organic_trend","type":"line","title":"Органические сессии","labels":["2026-09-01","2026-09-02"],"series":[{"name":"organic_sessions","values":[40,52]}]}]
```
MD;

        $parsed = $this->parser->handle($raw);

        $this->assertSame(
            "## Краткое резюме\n\nРост органики.\n\n{{chart:organic_trend}}",
            $parsed['reply'],
        );
        $this->assertCount(1, $parsed['charts']);
        $this->assertSame('organic_trend', $parsed['charts'][0]['id']);
        $this->assertSame('line', $parsed['charts'][0]['type']);
        $this->assertSame([40, 52], $parsed['charts'][0]['series'][0]['values']);
    }

    public function test_keeps_full_reply_when_json_is_invalid(): void
    {
        $raw = "## Отчёт\n\n```charts-json\n{not-json}\n```";

        $parsed = $this->parser->handle($raw);

        $this->assertSame($raw, $parsed['reply']);
        $this->assertSame([], $parsed['charts']);
    }

    public function test_drops_invalid_chart_types_and_mismatched_series(): void
    {
        $raw = <<<'MD'
Текст

```charts-json
[
  {"id":"ok","type":"bar","title":"Клики","labels":["A","B"],"series":[{"name":"clicks","values":[1,2]}]},
  {"id":"bad_type","type":"radar","title":"X","labels":["A"],"series":[{"name":"v","values":[1]}]},
  {"id":"bad_len","type":"line","title":"Y","labels":["A","B"],"series":[{"name":"v","values":[1]}]},
  {"id":"BadId","type":"line","title":"Z","labels":["A"],"series":[{"name":"v","values":[1]}]}
]
```
MD;

        $parsed = $this->parser->handle($raw);

        $this->assertSame('Текст', $parsed['reply']);
        $this->assertCount(1, $parsed['charts']);
        $this->assertSame('ok', $parsed['charts'][0]['id']);
    }

    public function test_uses_last_charts_json_block(): void
    {
        $raw = <<<'MD'
Первый

```charts-json
[{"id":"first","type":"line","title":"A","labels":["1"],"series":[{"name":"n","values":[1]}]}]
```

Второй {{chart:second}}

```charts-json
[{"id":"second","type":"pie","title":"Устройства","labels":["MOBILE","DESKTOP"],"series":[{"name":"clicks","values":[10,5]}]}]
```
MD;

        $parsed = $this->parser->handle($raw);

        $this->assertStringContainsString('{{chart:second}}', $parsed['reply']);
        $this->assertStringNotContainsString('charts-json', $parsed['reply']);
        $this->assertCount(1, $parsed['charts']);
        $this->assertSame('second', $parsed['charts'][0]['id']);
        $this->assertSame('pie', $parsed['charts'][0]['type']);
    }

    public function test_rejects_pie_with_multiple_series(): void
    {
        $raw = <<<'MD'
x

```charts-json
[{"id":"devices","type":"pie","title":"Устройства","labels":["A","B"],"series":[{"name":"a","values":[1,2]},{"name":"b","values":[3,4]}]}]
```
MD;

        $parsed = $this->parser->handle($raw);

        $this->assertSame('x', $parsed['reply']);
        $this->assertSame([], $parsed['charts']);
    }
}
