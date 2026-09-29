<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pagespeed_integrations', function (Blueprint $table) {
            $table->json('page_urls')->nullable()->after('strategy');
        });

        Schema::table('site_pagespeed_lab_snapshots', function (Blueprint $table) {
            $table->unsignedTinyInteger('accessibility_score')->nullable()->after('performance_score');
            $table->unsignedTinyInteger('best_practices_score')->nullable()->after('accessibility_score');
            $table->unsignedTinyInteger('seo_score')->nullable()->after('best_practices_score');
            $table->json('payload')->nullable()->after('speed_index_ms');
        });

        Schema::table('site_crux_snapshots', function (Blueprint $table) {
            $table->string('overall_category', 32)->nullable()->after('form_factor');
            $table->json('metrics')->nullable()->after('ttfb_p75_ms');
        });
    }

    public function down(): void
    {
        Schema::table('site_pagespeed_integrations', function (Blueprint $table) {
            $table->dropColumn('page_urls');
        });

        Schema::table('site_pagespeed_lab_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'accessibility_score',
                'best_practices_score',
                'seo_score',
                'payload',
            ]);
        });

        Schema::table('site_crux_snapshots', function (Blueprint $table) {
            $table->dropColumn(['overall_category', 'metrics']);
        });
    }
};
