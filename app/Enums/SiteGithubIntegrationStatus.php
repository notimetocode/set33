<?php

namespace App\Enums;

enum SiteGithubIntegrationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Не настроено',
            self::Active => 'Активна',
        };
    }
}
