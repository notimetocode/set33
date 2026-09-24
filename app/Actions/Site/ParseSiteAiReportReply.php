<?php

namespace App\Actions\Site;

class ParseSiteAiReportReply
{
    private const int MAX_CHARTS = 6;

    private const array ALLOWED_TYPES = ['line', 'bar', 'pie'];

    /**
     * @return array{reply: string, charts: list<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     labels: list<string>,
     *     series: list<array{name: string, values: list<float|int>}>
     * }>}
     */
    public function handle(string $rawReply): array
    {
        $trimmed = trim($rawReply);

        if ($trimmed === '') {
            return [
                'reply' => '',
                'charts' => [],
            ];
        }

        $fenceOpen = '```charts-json';
        $blockStart = strrpos($trimmed, $fenceOpen);

        if ($blockStart === false) {
            return [
                'reply' => $trimmed,
                'charts' => [],
            ];
        }

        $afterOpen = ltrim(substr($trimmed, $blockStart + strlen($fenceOpen)), "\r\n");
        $closePos = strpos($afterOpen, '```');

        if ($closePos === false) {
            return [
                'reply' => $trimmed,
                'charts' => [],
            ];
        }

        $jsonChunk = trim(substr($afterOpen, 0, $closePos));
        $decoded = json_decode($jsonChunk, true);

        if (! is_array($decoded)) {
            return [
                'reply' => $trimmed,
                'charts' => [],
            ];
        }

        return [
            'reply' => $this->stripChartsJsonBlocks($trimmed),
            'charts' => $this->normalizeCharts($decoded),
        ];
    }

    private function stripChartsJsonBlocks(string $reply): string
    {
        $cleaned = preg_replace('/```charts-json\s*\n.*?```/s', '', $reply) ?? $reply;

        return trim(preg_replace("/\n{3,}/", "\n\n", $cleaned) ?? $cleaned);
    }

    /**
     * @param  array<mixed>  $decoded
     * @return list<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     labels: list<string>,
     *     series: list<array{name: string, values: list<float|int>}>
     * }>
     */
    private function normalizeCharts(array $decoded): array
    {
        $items = array_is_list($decoded) ? $decoded : [];
        $charts = [];
        $seenIds = [];

        foreach ($items as $item) {
            if (count($charts) >= self::MAX_CHARTS) {
                break;
            }

            if (! is_array($item)) {
                continue;
            }

            $chart = $this->normalizeChart($item);

            if ($chart === null || isset($seenIds[$chart['id']])) {
                continue;
            }

            $seenIds[$chart['id']] = true;
            $charts[] = $chart;
        }

        return $charts;
    }

    /**
     * @param  array<mixed>  $item
     * @return array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     labels: list<string>,
     *     series: list<array{name: string, values: list<float|int>}>
     * }|null
     */
    private function normalizeChart(array $item): ?array
    {
        $id = $item['id'] ?? null;
        $type = $item['type'] ?? null;
        $title = $item['title'] ?? null;
        $labels = $item['labels'] ?? null;
        $series = $item['series'] ?? null;

        if (! is_string($id) || ! preg_match('/^[a-z0-9_]+$/', $id)) {
            return null;
        }

        if (! is_string($type) || ! in_array($type, self::ALLOWED_TYPES, true)) {
            return null;
        }

        if (! is_string($title)) {
            return null;
        }

        $title = trim($title);

        if ($title === '') {
            return null;
        }

        if (! is_array($labels) || $labels === [] || ! array_is_list($labels)) {
            return null;
        }

        $normalizedLabels = [];

        foreach ($labels as $label) {
            if (! is_string($label) && ! is_numeric($label)) {
                return null;
            }

            $normalizedLabels[] = (string) $label;
        }

        if (! is_array($series) || $series === [] || ! array_is_list($series)) {
            return null;
        }

        $normalizedSeries = [];
        $labelCount = count($normalizedLabels);

        foreach ($series as $serie) {
            if (! is_array($serie)) {
                return null;
            }

            $name = $serie['name'] ?? null;
            $values = $serie['values'] ?? null;

            if (! is_string($name) || trim($name) === '') {
                return null;
            }

            if (! is_array($values) || ! array_is_list($values) || count($values) !== $labelCount) {
                return null;
            }

            $normalizedValues = [];

            foreach ($values as $value) {
                if (! is_numeric($value)) {
                    return null;
                }

                $normalizedValues[] = str_contains((string) $value, '.')
                    ? (float) $value
                    : (int) $value;
            }

            $normalizedSeries[] = [
                'name' => trim($name),
                'values' => $normalizedValues,
            ];
        }

        if ($type === 'pie' && count($normalizedSeries) !== 1) {
            return null;
        }

        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'labels' => $normalizedLabels,
            'series' => $normalizedSeries,
        ];
    }
}
