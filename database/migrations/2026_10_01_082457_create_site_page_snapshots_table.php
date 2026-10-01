<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_page_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('final_url', 768)->nullable();
            $table->string('page_title', 512)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 512)->nullable();
            $table->string('og_title', 512)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image_url', 768)->nullable();
            $table->string('canonical_url', 768)->nullable();
            $table->string('html_lang', 32)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url'], 'site_page_snapshots_site_url_unique');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_page_snapshots');
    }
};
