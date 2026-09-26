<?php

namespace App\Enums;

enum PageSpeedStrategy: string
{
    case Mobile = 'mobile';
    case Desktop = 'desktop';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Mobile => 'Mobile',
            self::Desktop => 'Desktop',
            self::Both => 'Mobile и Desktop',
        };
    }

    /**
     * @return list<string>
     */
    public function runStrategies(): array
    {
        return match ($this) {
            self::Mobile => ['mobile'],
            self::Desktop => ['desktop'],
            self::Both => ['mobile', 'desktop'],
        };
    }

    /**
     * @return list<string>
     */
    public function cruxFormFactors(): array
    {
        return match ($this) {
            self::Mobile => ['PHONE'],
            self::Desktop => ['DESKTOP'],
            self::Both => ['PHONE', 'DESKTOP'],
        };
    }
}
