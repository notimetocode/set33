<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_search_console_sitemaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('path', 768);
            $table->string('type', 64)->nullable();
            $table->boolean('is_pending')->default(false);
            $table->boolean('is_sitemaps_index')->default(false);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('last_submitted_at')->nullable();
            $table->unsignedBigInteger('errors')->default(0);
            $table->unsignedBigInteger('warnings')->default(0);
            $table->json('contents')->nullable();
            $table->timestamps();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_search_console_sitemaps');
    }
};
