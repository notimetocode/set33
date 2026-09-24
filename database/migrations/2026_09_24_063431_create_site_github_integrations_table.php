<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_github_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('github_connection_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('repository_id')->nullable();
            $table->string('repository_owner');
            $table->string('repository_name');
            $table->string('repository_full_name');
            $table->string('default_branch')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_github_integrations');
    }
};
