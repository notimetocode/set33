<?php

namespace App\Enums;

enum SiteGoogleIntegrationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Не настроено',
            self::Active => 'Активна',
            self::Error => 'Ошибка',
        };
    }
}
