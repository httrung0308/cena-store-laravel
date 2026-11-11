<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('title', 150);
            $table->string('subtitle', 255)->nullable();
            $table->string('image_path', 255);
            $table->string('mobile_image_path', 255)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('cta_text', 50)->nullable();
            $table->smallInteger('sort_order')->unsigned()->default(1);
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['is_active', 'sort_order', 'id'], 'banners_active_sort_idx');
        });

        DB::statement('ALTER TABLE `banners` ADD CONSTRAINT `banners_is_active_boolean_check` CHECK (`is_active` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
