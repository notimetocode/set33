<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('screen_page_views')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_analytics_daily');
    }
};
