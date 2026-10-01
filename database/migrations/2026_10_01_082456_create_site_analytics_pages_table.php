<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_analytics_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('page_path', 500);
            $table->unsignedSmallInteger('rank')->default(1);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('screen_page_views')->default(0);
            $table->unsignedInteger('total_users')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'period_from', 'period_to', 'page_path'], 'site_analytics_pages_unique');
            $table->index(['site_id', 'period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_analytics_pages');
    }
};
