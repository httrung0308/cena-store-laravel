<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('author_id')->unsigned();
            $table->string('title', 200);
            $table->string('slug', 220);
            $table->string('summary', 500);
            $table->text('body');
            $table->string('cover_path', 500)->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['slug']);
            $table->index(['status', 'published_at']);
            $table->foreign('author_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE `articles` ADD CONSTRAINT `articles_status_allowed_check` CHECK (CAST(`status` AS BINARY) IN (\'DRAFT\', \'PUBLISHED\', \'HIDDEN\'))');
        DB::statement('ALTER TABLE `articles` ADD CONSTRAINT `articles_published_at_check` CHECK (`status` <> \'PUBLISHED\' OR `published_at` IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
