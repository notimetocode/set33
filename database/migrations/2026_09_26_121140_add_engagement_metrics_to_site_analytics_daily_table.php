<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_analytics_daily', function (Blueprint $table) {
            $table->unsignedInteger('engaged_sessions')->default(0)->after('organic_new_users');
            $table->decimal('engagement_rate', 8, 6)->default(0)->after('engaged_sessions');
            $table->decimal('bounce_rate', 8, 6)->default(0)->after('engagement_rate');
            $table->decimal('average_session_duration', 12, 2)->default(0)->after('bounce_rate');
            $table->unsignedInteger('event_count')->default(0)->after('average_session_duration');
            $table->unsignedInteger('organic_engaged_sessions')->default(0)->after('event_count');
        });
    }

    public function down(): void
    {
        Schema::table('site_analytics_daily', function (Blueprint $table) {
            $table->dropColumn([
                'engaged_sessions',
                'engagement_rate',
                'bounce_rate',
                'average_session_duration',
                'event_count',
                'organic_engaged_sessions',
            ]);
        });
    }
};
