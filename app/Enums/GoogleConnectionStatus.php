<?php

namespace App\Enums;

enum GoogleConnectionStatus: string
{
    case Active = 'active';
    case NeedsReauth = 'needs_reauth';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Подключено',
            self::NeedsReauth => 'Нужна повторная авторизация',
        };
    }
}
