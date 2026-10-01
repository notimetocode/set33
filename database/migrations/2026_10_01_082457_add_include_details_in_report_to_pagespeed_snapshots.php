<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pagespeed_lab_snapshots', function (Blueprint $table) {
            $table->boolean('include_details_in_report')->default(false)->after('payload');
        });

        Schema::table('site_crux_snapshots', function (Blueprint $table) {
            $table->boolean('include_details_in_report')->default(false)->after('metrics');
        });
    }

    public function down(): void
    {
        Schema::table('site_pagespeed_lab_snapshots', function (Blueprint $table) {
            $table->dropColumn('include_details_in_report');
        });

        Schema::table('site_crux_snapshots', function (Blueprint $table) {
            $table->dropColumn('include_details_in_report');
        });
    }
};
