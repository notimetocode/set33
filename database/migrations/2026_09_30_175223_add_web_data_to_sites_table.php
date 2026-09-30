<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('web_data_status')->nullable()->after('url');
            $table->string('page_title')->nullable()->after('web_data_status');
            $table->text('meta_description')->nullable()->after('page_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            $table->string('og_title')->nullable()->after('meta_keywords');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image_url', 2048)->nullable()->after('og_description');
            $table->string('canonical_url', 2048)->nullable()->after('og_image_url');
            $table->string('html_lang', 32)->nullable()->after('canonical_url');
            $table->string('favicon_path')->nullable()->after('html_lang');
            $table->string('favicon_source_url', 2048)->nullable()->after('favicon_path');
            $table->longText('robots_txt')->nullable()->after('favicon_source_url');
            $table->timestamp('web_data_fetched_at')->nullable()->after('robots_txt');
            $table->text('web_data_error')->nullable()->after('web_data_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'web_data_status',
                'page_title',
                'meta_description',
                'meta_keywords',
                'og_title',
                'og_description',
                'og_image_url',
                'canonical_url',
                'html_lang',
                'favicon_path',
                'favicon_source_url',
                'robots_txt',
                'web_data_fetched_at',
                'web_data_error',
            ]);
        });
    }
};
