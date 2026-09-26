<?php

namespace App\Enums;

enum AiReportVisibility: string
{
    case Private = 'private';
    case Link = 'link';
    case Password = 'password';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Приватный',
            self::Link => 'Доступен по ссылке',
            self::Password => 'Доступен по ссылке с паролем',
        };
    }

    public function isShared(): bool
    {
        return $this !== self::Private;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $visibility): array => [
                'value' => $visibility->value,
                'label' => $visibility->label(),
            ],
            self::cases(),
        );
    }
}
