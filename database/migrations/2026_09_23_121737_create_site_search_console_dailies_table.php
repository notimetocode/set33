<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_search_console_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 6)->default(0);
            $table->decimal('position', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_search_console_daily');
    }
};
