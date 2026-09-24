<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_analytics_daily', function (Blueprint $table) {
            $table->unsignedInteger('organic_sessions')->default(0)->after('screen_page_views');
            $table->unsignedInteger('organic_total_users')->default(0)->after('organic_sessions');
            $table->unsignedInteger('organic_new_users')->default(0)->after('organic_total_users');
        });
    }

    public function down(): void
    {
        Schema::table('site_analytics_daily', function (Blueprint $table) {
            $table->dropColumn([
                'organic_sessions',
                'organic_total_users',
                'organic_new_users',
            ]);
        });
    }
};
