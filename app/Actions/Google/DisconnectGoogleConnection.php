<?php

namespace App\Actions\Google;

use App\Models\GoogleConnection;
use App\Services\Google\GoogleApiClient;
use Illuminate\Support\Facades\DB;

class DisconnectGoogleConnection
{
    public function __construct(
        private readonly GoogleApiClient $googleApiClient,
    ) {}

    public function handle(GoogleConnection $connection): void
    {
        try {
            $this->googleApiClient->revoke($connection);
        } catch (\Throwable) {
            // Best-effort revoke; local disconnect still proceeds.
        }

        DB::transaction(function () use ($connection): void {
            $connection->siteIntegrations()->delete();
            $connection->pagespeedIntegrations()->delete();
            $connection->delete();
        });
    }
}
