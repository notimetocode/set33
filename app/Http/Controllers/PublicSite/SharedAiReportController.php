<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\Site\UnlockSharedAiReport;
use App\Enums\AiReportVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\UnlockSharedAiReportRequest;
use App\Http\Resources\App\SharedAiReportResource;
use App\Models\SiteAiReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SharedAiReportController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $report = $this->findSharedReport($token);

        if ($report->visibility === AiReportVisibility::Password
            && ! $this->isUnlocked($request, $token)
        ) {
            return view('public.ai-reports.password', [
                'token' => $token,
            ]);
        }

        $report->loadMissing('site:id,name');

        return view('public.ai-reports.show', [
            'report' => $report,
            'payload' => (new SharedAiReportResource($report))->resolve(),
        ]);
    }

    public function unlock(
        UnlockSharedAiReportRequest $request,
        string $token,
        UnlockSharedAiReport $unlock,
    ): RedirectResponse {
        $report = $this->findSharedReport($token);

        abort_unless($report->visibility === AiReportVisibility::Password, 404);

        $unlock->handle($report, $request->validated('password'));

        $request->session()->put($this->sessionKey($token), true);

        return redirect()->route('public.ai-reports.show', ['token' => $token]);
    }

    private function findSharedReport(string $token): SiteAiReport
    {
        $report = SiteAiReport::query()
            ->where('share_token', $token)
            ->firstOrFail();

        abort_unless($report->visibility->isShared(), 404);

        return $report;
    }

    private function isUnlocked(Request $request, string $token): bool
    {
        return (bool) $request->session()->get($this->sessionKey($token), false);
    }

    private function sessionKey(string $token): string
    {
        return 'ai_report_unlocked.'.$token;
    }
}
