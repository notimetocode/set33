<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pagespeed_lab_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url', 768);
            $table->string('strategy', 16);
            $table->timestamp('fetched_at');
            $table->unsignedTinyInteger('performance_score')->nullable();
            $table->unsignedInteger('lcp_ms')->nullable();
            $table->unsignedInteger('inp_ms')->nullable();
            $table->decimal('cls', 8, 4)->nullable();
            $table->unsignedInteger('fcp_ms')->nullable();
            $table->unsignedInteger('ttfb_ms')->nullable();
            $table->unsignedInteger('tbt_ms')->nullable();
            $table->unsignedInteger('speed_index_ms')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pagespeed_lab_snapshots');
    }
};
