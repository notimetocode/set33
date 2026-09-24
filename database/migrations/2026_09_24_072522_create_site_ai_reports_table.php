<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_ai_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ai_service_name');
            $table->string('ai_service_type', 32);
            $table->string('model')->nullable();
            $table->date('period_from');
            $table->date('period_to');
            $table->longText('reply');
            $table->json('usage')->nullable();
            $table->json('data_counts')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_ai_reports');
    }
};
