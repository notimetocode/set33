<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_crux_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16);
            $table->string('url', 768);
            $table->string('form_factor', 16);
            $table->date('collection_period_start')->nullable();
            $table->date('collection_period_end')->nullable();
            $table->unsignedInteger('lcp_p75_ms')->nullable();
            $table->unsignedInteger('inp_p75_ms')->nullable();
            $table->decimal('cls_p75', 8, 4)->nullable();
            $table->unsignedInteger('fcp_p75_ms')->nullable();
            $table->unsignedInteger('ttfb_p75_ms')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['site_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_crux_snapshots');
    }
};
