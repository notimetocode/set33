<?php

namespace App\Enums;

enum SitePageSpeedIntegrationStatus: string
{
    case Active = 'active';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активна',
            self::Error => 'Ошибка',
        };
    }
}
