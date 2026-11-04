<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Was this article helpful?" answers: one row per article and reader (a keyed hash of the user or browser
     * session, never the raw id), so a reader can change their answer but not vote twice.
     */
    public function up(): void
    {
        Schema::create('article_feedback', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('article_id')->unsigned();
            $table->char('voter_hash', 64);
            $table->boolean('is_helpful');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['article_id', 'voter_hash']);
            $table->index(['article_id', 'is_helpful']);
            $table->foreign('article_id')->references('id')->on('articles')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_feedback');
    }
};
