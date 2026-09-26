<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_url_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('inspected_url', 768);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('verdict', 64)->nullable();
            $table->string('coverage_state', 255)->nullable();
            $table->string('indexing_state', 64)->nullable();
            $table->string('page_fetch_state', 64)->nullable();
            $table->string('robots_txt_state', 64)->nullable();
            $table->string('crawled_as', 64)->nullable();
            $table->timestamp('last_crawl_time')->nullable();
            $table->string('google_canonical', 768)->nullable();
            $table->string('user_canonical', 768)->nullable();
            $table->string('inspection_result_link', 768)->nullable();
            $table->json('referring_urls')->nullable();
            $table->json('sitemaps')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_url_inspections');
    }
};
