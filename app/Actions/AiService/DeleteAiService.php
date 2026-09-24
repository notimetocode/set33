<?php

namespace App\Actions\AiService;

use App\Models\AiService;

class DeleteAiService
{
    public function handle(AiService $aiService): void
    {
        $aiService->delete();
    }
}
