<?php

namespace App\Enums;

enum SearchConsoleDimension: string
{
    case Query = 'query';
    case Page = 'page';
    case Device = 'device';
    case Country = 'country';

    public function label(): string
    {
        return match ($this) {
            self::Query => 'Запрос',
            self::Page => 'Страница',
            self::Device => 'Устройство',
            self::Country => 'Страна',
        };
    }

    public function apiDimension(): string
    {
        return $this->value;
    }
}
