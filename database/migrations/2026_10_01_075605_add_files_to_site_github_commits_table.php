<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_github_commits', function (Blueprint $table) {
            $table->json('files')->nullable()->after('committer_date');
            $table->json('stats')->nullable()->after('files');
            $table->boolean('files_incomplete')->default(false)->after('stats');
            $table->timestamp('files_fetched_at')->nullable()->after('files_incomplete');
        });
    }

    public function down(): void
    {
        Schema::table('site_github_commits', function (Blueprint $table) {
            $table->dropColumn(['files', 'stats', 'files_incomplete', 'files_fetched_at']);
        });
    }
};
