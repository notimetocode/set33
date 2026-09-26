<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_ai_reports', function (Blueprint $table) {
            $table->string('visibility', 32)->default('private')->after('data_counts');
            $table->string('share_token', 64)->nullable()->unique()->after('visibility');
            $table->string('share_password')->nullable()->after('share_token');
        });
    }

    public function down(): void
    {
        Schema::table('site_ai_reports', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['visibility', 'share_token', 'share_password']);
        });
    }
};
