<?php

namespace App\Enums;

enum SearchConsoleDimension: string
{
    case Query = 'query';
    case Page = 'page';
    case Device = 'device';
    case Country = 'country';
    case SearchAppearance = 'search_appearance';

    public function label(): string
    {
        return match ($this) {
            self::Query => 'Запрос',
            self::Page => 'Страница',
            self::Device => 'Устройство',
            self::Country => 'Страна',
            self::SearchAppearance => 'Тип отображения',
        };
    }

    public function apiDimension(): string
    {
        return match ($this) {
            self::SearchAppearance => 'searchAppearance',
            default => $this->value,
        };
    }
}
