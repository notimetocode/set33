<?php

namespace App\Actions\Site;

use App\Enums\AiReportVisibility;
use App\Models\SiteAiReport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UpdateSiteAiReportSharing
{
    /**
     * @param  array{visibility: string, password?: string|null}  $data
     */
    public function handle(SiteAiReport $report, array $data): SiteAiReport
    {
        $visibility = AiReportVisibility::from($data['visibility']);
        $password = $data['password'] ?? null;

        $report->visibility = $visibility;

        if (! $visibility->isShared()) {
            $report->share_token = null;
            $report->share_password = null;
            $report->save();

            return $report->refresh();
        }

        if (! filled($report->share_token)) {
            $report->share_token = $this->generateShareToken();
        }

        if ($visibility === AiReportVisibility::Password) {
            if (filled($password)) {
                $report->share_password = Hash::make($password);
            }
        } else {
            $report->share_password = null;
        }

        $report->save();

        return $report->refresh();
    }

    private function generateShareToken(): string
    {
        do {
            $token = Str::random(40);
        } while (SiteAiReport::query()->where('share_token', $token)->exists());

        return $token;
    }
}
