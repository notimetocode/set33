<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_github_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('sha', 64);
            $table->text('message');
            $table->string('html_url')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->timestamp('author_date')->nullable();
            $table->string('committer_name')->nullable();
            $table->string('committer_email')->nullable();
            $table->timestamp('committer_date')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'sha']);
            $table->index(['site_id', 'author_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_github_commits');
    }
};
