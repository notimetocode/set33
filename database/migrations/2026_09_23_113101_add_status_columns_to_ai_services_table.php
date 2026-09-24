<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_services', function (Blueprint $table) {
            $table->string('status')->default('unchecked')->after('settings');
            $table->text('status_message')->nullable()->after('status');
            $table->timestamp('status_checked_at')->nullable()->after('status_message');
        });
    }

    public function down(): void
    {
        Schema::table('ai_services', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_message', 'status_checked_at']);
        });
    }
};
