<?php

namespace App\Services\Discover;

final class DiscoverCheckCatalog
{
    public const AVAILABILITY = 'availability';

    public const INDEXING = 'indexing';

    public const MAX_IMAGE_PREVIEW = 'max_image_preview';

    public const CANONICAL = 'canonical';

    public const AMP = 'amp';

    public const SITEMAPS = 'sitemaps';

    public const CORE_WEB_VITALS = 'core_web_vitals';

    public const COVER_IMAGE = 'cover_image';

    public const AUTHOR = 'author';

    public const DATES = 'dates';

    public const NEWS_ARTICLE = 'news_article';

    public const OPEN_GRAPH = 'open_graph';

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return [
            self::AVAILABILITY,
            self::INDEXING,
            self::MAX_IMAGE_PREVIEW,
            self::CANONICAL,
            self::AMP,
            self::SITEMAPS,
            self::CORE_WEB_VITALS,
            self::COVER_IMAGE,
            self::AUTHOR,
            self::DATES,
            self::NEWS_ARTICLE,
            self::OPEN_GRAPH,
        ];
    }
}
