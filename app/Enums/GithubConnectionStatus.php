<?php

namespace App\Enums;

enum GithubConnectionStatus: string
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
