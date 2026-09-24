<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_search_console_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('dimension', 32);
            $table->string('value', 2048);
            $table->unsignedSmallInteger('rank')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 6)->default(0);
            $table->decimal('position', 8, 2)->default(0);
            $table->timestamps();

            $table->index(
                ['site_id', 'period_from', 'period_to', 'dimension', 'rank'],
                'site_gsc_dimensions_period_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_search_console_dimensions');
    }
};
