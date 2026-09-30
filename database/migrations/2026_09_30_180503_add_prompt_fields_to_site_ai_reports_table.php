<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_ai_reports', function (Blueprint $table) {
            $table->boolean('use_system_prompt')->default(true)->after('period_to');
            $table->longText('prompt')->nullable()->after('use_system_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('site_ai_reports', function (Blueprint $table) {
            $table->dropColumn(['use_system_prompt', 'prompt']);
        });
    }
};
