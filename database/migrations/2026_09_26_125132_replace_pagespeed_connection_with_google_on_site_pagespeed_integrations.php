<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_pagespeed_integrations')->delete();

        Schema::table('site_pagespeed_integrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pagespeed_connection_id');
        });

        Schema::table('site_pagespeed_integrations', function (Blueprint $table) {
            $table->foreignId('google_connection_id')
                ->after('site_id')
                ->constrained('google_connections')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('page_speed_connections');
    }

    public function down(): void
    {
        Schema::create('page_speed_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('api_key');
            $table->string('status', 32)->default('active');
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        DB::table('site_pagespeed_integrations')->delete();

        Schema::table('site_pagespeed_integrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('google_connection_id');
        });

        Schema::table('site_pagespeed_integrations', function (Blueprint $table) {
            $table->foreignId('pagespeed_connection_id')
                ->after('site_id')
                ->constrained('page_speed_connections')
                ->cascadeOnDelete();
        });
    }
};
