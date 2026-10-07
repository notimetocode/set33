<?php

namespace App\Services\SiteAudit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class PageContentAnalyzer
{
    private const MAX_HEADINGS = 40;

    /**
     * @var list<string>
     */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'if', 'then', 'else', 'when', 'at', 'by', 'for',
        'with', 'about', 'against', 'between', 'into', 'through', 'during', 'before', 'after',
        'above', 'below', 'to', 'from', 'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under',
        'again', 'further', 'once', 'here', 'there', 'all', 'any', 'both', 'each', 'few', 'more',
        'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than',
        'too', 'very', 'can', 'will', 'just', 'don', 'should', 'now', 'is', 'are', 'was', 'were',
        'be', 'been', 'been', 'of', 'as', 'it', 'this', 'that', 'these', 'those', 'i', 'you', 'he',
        'she', 'we', 'they', 'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'its', 'our',
        'their', 'what', 'which', 'who', 'whom', 'how', 'do', 'does', 'did', 'have', 'has', 'had',
        'и', 'в', 'во', 'не', 'что', 'он', 'на', 'я', 'с', 'со', 'как', 'а', 'то', 'все', 'она',
        'так', 'его', 'но', 'да', 'ты', 'к', 'у', 'же', 'вы', 'за', 'бы', 'по', 'только', 'ее',
        'мне', 'было', 'вот', 'от', 'меня', 'еще', 'нет', 'о', 'из', 'ему', 'теперь', 'когда',
        'даже', 'ну', 'вдруг', 'ли', 'если', 'уже', 'или', 'ни', 'быть', 'был', 'него', 'до',
        'вас', 'нибудь', 'опять', 'уж', 'вам', 'ведь', 'там', 'потом', 'себя', 'ничего', 'ей',
        'может', 'они', 'тут', 'где', 'есть', 'надо', 'ней', 'для', 'мы', 'тебя', 'их', 'чем',
        'была', 'сам', 'чтоб', 'без', 'будто', 'чего', 'раз', 'тоже', 'себе', 'под', 'будет',
        'ж', 'тогда', 'кто', 'этот', 'того', 'потому', 'этого', 'какой', 'совсем', 'ним', 'здесь',
        'этом', 'один', 'почти', 'мой', 'тем', 'чтобы', 'нее', 'сейчас', 'были', 'куда', 'зачем',
        'всех', 'никогда', 'можно', 'при', 'наконец', 'два', 'об', 'другой', 'хоть', 'после',
        'над', 'больше', 'тот', 'через', 'эти', 'нас', 'про', 'всего', 'них', 'какая', 'много',
        'разве', 'три', 'эту', 'моя', 'впрочем', 'хорошо', 'свою', 'этой', 'перед', 'иногда',
        'лучше', 'чуть', 'том', 'нельзя', 'такой', 'им', 'более', 'всегда', 'конечно', 'всю',
        'между', 'это', 'также', 'ещё',
    ];

    /**
     * Soft heuristic markers only — not a classifier.
     *
     * @var list<string>
     */
    private const ADULT_MARKERS = [
        'porn', 'porno', 'xxx', 'adult video', 'sex shop', 'webcam girls',
        'порно', 'эротика', 'секс шоп', 'xxx видео',
    ];

    public function __construct(
        private HtmlDocumentParser $parser = new HtmlDocumentParser,
    ) {}

    /**
     * @return array{
     *     title: array{text: ?string, length: int, count: int},
     *     description: array{text: ?string, length: int, count: int},
     *     headings: array{counts: array<string, int>, items: list<array{level: int, text: string}>},
     *     text_length: int,
     *     word_count: int,
     *     nausea: float,
     *     html_size_bytes: int,
     *     external_links: array{total: int, indexable: int},
     *     internal_links: array{total: int, indexable: int},
     *     adult_content: bool,
     *     open_graph: array{present: bool, title: ?string, description: ?string, image_url: ?string},
     *     schema_org: array{
     *         present: bool,
     *         types: list<string>,
     *         formats: list<string>,
     *         json_ld_count: int,
     *         microdata_count: int,
     *         items: list<array{type: string, format: string, name: ?string, url: ?string}>
     *     }
     * }
     */
    public function analyze(string $html, string $baseUrl): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $titleNodes = $xpath->query('//title');
        $titleText = $this->normalizeText($titleNodes?->item(0)?->textContent);
        $descriptionNodes = $xpath->query(
            '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]',
        );
        $descriptionText = $this->normalizeText($descriptionNodes?->item(0)?->getAttribute('content'), 2000);

        $visibleText = $this->extractVisibleText($html);
        $words = $this->tokenizeWords($visibleText);
        $headings = $this->extractHeadings($xpath);
        $links = $this->extractLinks($xpath, $baseUrl);
        $ogTitle = $this->parser->metaProperty($xpath, ['og:title']);
        $ogDescription = $this->parser->metaProperty($xpath, ['og:description']);
        $ogImage = $this->parser->absolutizeUrl(
            $this->parser->metaProperty($xpath, ['og:image', 'og:image:url']),
            $baseUrl,
        );

        return [
            'title' => [
                'text' => $titleText,
                'length' => $this->charLength($titleText),
                'count' => $titleNodes === false ? 0 : $titleNodes->length,
            ],
            'description' => [
                'text' => $descriptionText,
                'length' => $this->charLength($descriptionText),
                'count' => $descriptionNodes === false ? 0 : $descriptionNodes->length,
            ],
            'headings' => $headings,
            'text_length' => $this->charLength($visibleText),
            'word_count' => count($words),
            'nausea' => $this->academicNausea($words),
            'html_size_bytes' => strlen($html),
            'external_links' => $links['external'],
            'internal_links' => $links['internal'],
            'adult_content' => $this->detectAdultContent($visibleText.' '.$html),
            'open_graph' => [
                'present' => filled($ogTitle) || filled($ogDescription) || filled($ogImage),
                'title' => $ogTitle,
                'description' => $ogDescription,
                'image_url' => $ogImage,
            ],
            'schema_org' => $this->detectSchemaOrg($xpath),
        ];
    }

    /**
     * @return array{counts: array<string, int>, items: list<array{level: int, text: string}>}
     */
    private function extractHeadings(DOMXPath $xpath): array
    {
        $counts = [
            'h1' => 0,
            'h2' => 0,
            'h3' => 0,
            'h4' => 0,
            'h5' => 0,
            'h6' => 0,
        ];
        $items = [];

        foreach (range(1, 6) as $level) {
            $nodes = $xpath->query('//h'.$level);

            if ($nodes === false) {
                continue;
            }

            $counts['h'.$level] = $nodes->length;

            foreach ($nodes as $node) {
                if (count($items) >= self::MAX_HEADINGS) {
                    break 2;
                }

                $text = $this->normalizeText($node->textContent, 300);

                if ($text === null) {
                    continue;
                }

                $items[] = [
                    'level' => $level,
                    'text' => $text,
                ];
            }
        }

        return [
            'counts' => $counts,
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *     external: array{total: int, indexable: int},
     *     internal: array{total: int, indexable: int}
     * }
     */
    private function extractLinks(DOMXPath $xpath, string $baseUrl): array
    {
        $external = ['total' => 0, 'indexable' => 0];
        $internal = ['total' => 0, 'indexable' => 0];
        $baseHost = Str::lower((string) parse_url($baseUrl, PHP_URL_HOST));
        $nodes = $xpath->query('//a[@href]');

        if ($nodes === false || $baseHost === '') {
            return compact('external', 'internal');
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = trim($node->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || str_starts_with(Str::lower($href), 'javascript:')
                || str_starts_with(Str::lower($href), 'mailto:') || str_starts_with(Str::lower($href), 'tel:')) {
                continue;
            }

            $absolute = $this->parser->absolutizeUrl($href, $baseUrl);

            if ($absolute === null) {
                continue;
            }

            $host = Str::lower((string) parse_url($absolute, PHP_URL_HOST));

            if ($host === '') {
                continue;
            }

            $rel = Str::lower($node->getAttribute('rel'));
            $indexable = ! str_contains($rel, 'nofollow');
            $isInternal = $host === $baseHost || $host === 'www.'.$baseHost || 'www.'.$host === $baseHost;

            if ($isInternal) {
                $internal['total']++;
                if ($indexable) {
                    $internal['indexable']++;
                }
            } else {
                $external['total']++;
                if ($indexable) {
                    $external['indexable']++;
                }
            }
        }

        return compact('external', 'internal');
    }

    /**
     * @return array{
     *     present: bool,
     *     types: list<string>,
     *     formats: list<string>,
     *     json_ld_count: int,
     *     microdata_count: int,
     *     items: list<array{type: string, format: string, name: ?string, url: ?string}>
     * }
     */
    private function detectSchemaOrg(DOMXPath $xpath): array
    {
        $items = [];
        $jsonLdCount = 0;
        $microdataCount = 0;

        $itemtypes = $xpath->query('//*[@itemtype]');

        if ($itemtypes !== false) {
            foreach ($itemtypes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $value = trim($node->getAttribute('itemtype'));

                if ($value === '') {
                    continue;
                }

                $microdataCount++;
                $type = Str::afterLast($value, '/');
                $name = null;
                $url = null;

                $nameNodes = (new DOMXPath($node->ownerDocument))->query('.//*[@itemprop="name"]', $node);
                if ($nameNodes !== false && $nameNodes->item(0) !== null) {
                    $name = $this->normalizeText($nameNodes->item(0)->textContent, 200);
                }

                $urlNodes = (new DOMXPath($node->ownerDocument))->query('.//*[@itemprop="url"]', $node);
                if ($urlNodes !== false && $urlNodes->item(0) instanceof DOMElement) {
                    $url = $this->normalizeText(
                        $urlNodes->item(0)->getAttribute('href') ?: $urlNodes->item(0)->textContent,
                        500,
                    );
                }

                $items[] = [
                    'type' => $type,
                    'format' => 'microdata',
                    'name' => $name,
                    'url' => $url,
                ];
            }
        }

        $jsonLd = $xpath->query('//script[@type="application/ld+json"]');

        if ($jsonLd !== false) {
            foreach ($jsonLd as $node) {
                $raw = trim((string) $node->textContent);

                if ($raw === '') {
                    continue;
                }

                $jsonLdCount++;
                $decoded = json_decode($raw, true);

                if (! is_array($decoded)) {
                    $items[] = [
                        'type' => 'InvalidJSON',
                        'format' => 'json-ld',
                        'name' => null,
                        'url' => null,
                    ];

                    continue;
                }

                $items = [...$items, ...$this->collectJsonLdItems($decoded)];
            }
        }

        $types = [];
        $formats = [];

        foreach ($items as $item) {
            if (($item['type'] ?? '') !== '' && ($item['type'] ?? '') !== 'InvalidJSON') {
                $types[] = $item['type'];
            }

            if (($item['format'] ?? '') !== '') {
                $formats[] = $item['format'];
            }
        }

        $types = array_values(array_unique($types));
        $formats = array_values(array_unique($formats));

        return [
            'present' => $jsonLdCount > 0 || $microdataCount > 0,
            'types' => array_slice($types, 0, 20),
            'formats' => $formats,
            'json_ld_count' => $jsonLdCount,
            'microdata_count' => $microdataCount,
            'items' => array_slice($items, 0, 30),
        ];
    }

    /**
     * @param  array<mixed>  $payload
     * @return list<array{type: string, format: string, name: ?string, url: ?string}>
     */
    private function collectJsonLdItems(array $payload): array
    {
        $items = [];

        if (isset($payload['@graph']) && is_array($payload['@graph'])) {
            foreach ($payload['@graph'] as $item) {
                if (is_array($item)) {
                    $items = [...$items, ...$this->collectJsonLdItems($item)];
                }
            }

            return $items;
        }

        if (array_is_list($payload)) {
            foreach ($payload as $item) {
                if (is_array($item)) {
                    $items = [...$items, ...$this->collectJsonLdItems($item)];
                }
            }

            return $items;
        }

        $typeNames = [];

        if (isset($payload['@type'])) {
            $type = $payload['@type'];

            if (is_string($type)) {
                $typeNames[] = Str::afterLast($type, '/');
            } elseif (is_array($type)) {
                foreach ($type as $entry) {
                    if (is_string($entry)) {
                        $typeNames[] = Str::afterLast($entry, '/');
                    }
                }
            }
        }

        if ($typeNames === []) {
            return $items;
        }

        $name = null;

        foreach (['name', 'headline', 'alternateName'] as $key) {
            if (! empty($payload[$key]) && is_string($payload[$key])) {
                $name = $this->normalizeText($payload[$key], 200);
                break;
            }
        }

        $url = null;

        if (! empty($payload['url']) && is_string($payload['url'])) {
            $url = $this->normalizeText($payload['url'], 500);
        } elseif (! empty($payload['@id']) && is_string($payload['@id']) && str_starts_with($payload['@id'], 'http')) {
            $url = $this->normalizeText($payload['@id'], 500);
        }

        foreach ($typeNames as $typeName) {
            $items[] = [
                'type' => $typeName,
                'format' => 'json-ld',
                'name' => $name,
                'url' => $url,
            ];
        }

        return $items;
    }

    private function extractVisibleText(string $html): string
    {
        $stripped = preg_replace(
            '#<(script|style|noscript|svg|iframe)\b[^>]*>.*?</\1>#is',
            ' ',
            $html,
        ) ?? $html;

        $text = html_entity_decode(strip_tags($stripped), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->normalizeText($text, 500_000) ?? '';
    }

    /**
     * @return list<string>
     */
    private function tokenizeWords(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $normalized = Str::lower($text);
        preg_match_all('/[\p{L}\p{N}]{2,}/u', $normalized, $matches);

        return $matches[0] ?? [];
    }

    /**
     * @param  list<string>  $words
     */
    private function academicNausea(array $words): float
    {
        if ($words === []) {
            return 0.0;
        }

        $stop = array_fill_keys(self::STOP_WORDS, true);
        $filtered = [];

        foreach ($words as $word) {
            if (! isset($stop[$word])) {
                $filtered[] = $word;
            }
        }

        if ($filtered === []) {
            return 0.0;
        }

        $frequencies = array_count_values($filtered);
        $max = max($frequencies);

        return round(($max / count($filtered)) * 100, 2);
    }

    private function detectAdultContent(string $haystack): bool
    {
        $lower = Str::lower($haystack);

        foreach (self::ADULT_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function charLength(?string $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return mb_strlen($value);
    }

    private function normalizeText(?string $value, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $max, '');
    }
}
