<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classifications', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('classification_group', 20);
            $table->text('description')->nullable();
            $table->smallInteger('sort_order')->unsigned()->default(1);
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['classification_group', 'slug'], 'classifications_group_slug_unique');
            $table->index(['classification_group', 'is_active', 'sort_order'], 'classifications_group_active_sort_idx');
        });

        DB::statement("ALTER TABLE `classifications` ADD CONSTRAINT `classifications_group_allowed_check` CHECK (CAST(`classification_group` AS BINARY) IN ('SILHOUETTE', 'ACTIVITY'))");
        DB::statement('ALTER TABLE `classifications` ADD CONSTRAINT `classifications_is_active_boolean_check` CHECK (`is_active` IN (0, 1))');

        Schema::create('product_classifications', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('classification_id')->unsigned();
            $table->primary(['product_id', 'classification_id']);
            $table->index(['classification_id', 'product_id'], 'prod_class_class_prod_idx');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')->restrictOnUpdate();
            $table->foreign('classification_id')->references('id')->on('classifications')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_classifications');
        Schema::dropIfExists('classifications');
    }
};
