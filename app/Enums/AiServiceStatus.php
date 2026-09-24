<?php

namespace App\Enums;

enum AiServiceStatus: string
{
    case Unchecked = 'unchecked';
    case Ok = 'ok';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Unchecked => 'Не проверен',
            self::Ok => 'Работает',
            self::Error => 'Ошибка',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
