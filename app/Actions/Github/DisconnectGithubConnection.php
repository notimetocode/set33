<?php

namespace App\Actions\Github;

use App\Models\GithubConnection;
use App\Services\Github\GithubApiClient;
use Illuminate\Support\Facades\DB;

class DisconnectGithubConnection
{
    public function __construct(
        private readonly GithubApiClient $githubApiClient,
    ) {}

    public function handle(GithubConnection $connection): void
    {
        try {
            $this->githubApiClient->revoke($connection);
        } catch (\Throwable) {
            // Best-effort revoke; local disconnect still proceeds.
        }

        DB::transaction(function () use ($connection): void {
            $connection->siteIntegrations()->delete();
            $connection->delete();
        });
    }
}
