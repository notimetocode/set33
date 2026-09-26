<?php

namespace App\Actions\Site;

use App\Enums\AiReportVisibility;
use App\Models\SiteAiReport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UnlockSharedAiReport
{
    public function handle(SiteAiReport $report, string $password): void
    {
        if ($report->visibility !== AiReportVisibility::Password) {
            return;
        }

        if (! filled($report->share_password) || ! Hash::check($password, $report->share_password)) {
            throw ValidationException::withMessages([
                'password' => 'Неверный пароль.',
            ]);
        }
    }
}
