<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('gender_group', 20);
            $table->text('description')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->smallInteger('display_order')->unsigned()->default(1);
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['slug']);
            $table->index(['parent_id', 'display_order']);
            $table->foreign('parent_id')->references('id')->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('category_id')->unsigned();
            $table->string('name', 150);
            $table->string('slug', 180);
            $table->string('brand_line', 100)->nullable();
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->bigInteger('base_price')->unsigned();
            $table->bigInteger('promotion_price')->unsigned()->nullable();
            $table->smallInteger('warranty_months')->unsigned()->default(6);
            $table->smallInteger('exchange_days')->unsigned()->default(7);
            $table->decimal('rating_avg', 3, 2)->default('0.00');
            $table->integer('reviews_count')->unsigned()->default(0);
            $table->boolean('is_featured')->default(0);
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['slug']);
            $table->index(['category_id', 'is_active', 'created_at']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('product_colors', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('product_id')->unsigned();
            $table->string('style_color_code', 50);
            $table->string('color_name', 100);
            $table->string('color_key', 20);
            $table->char('primary_hex', 7);
            $table->smallInteger('display_order')->unsigned()->default(1);
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['style_color_code']);
            $table->index(['product_id', 'is_active', 'display_order']);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('product_color_id')->unsigned();
            $table->string('source_type', 20);
            $table->string('storage_path', 500)->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->string('alt_text', 255);
            $table->boolean('is_thumbnail')->default(0);
            $table->smallInteger('display_order')->unsigned()->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['product_color_id', 'display_order']);
            $table->foreign('product_color_id')->references('id')->on('product_colors')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('product_color_id')->unsigned();
            $table->string('size_value', 10);
            $table->decimal('size_numeric', 4, 1);
            $table->string('sku', 100);
            $table->bigInteger('price_override')->unsigned()->nullable();
            $table->integer('stock_qty')->unsigned()->default(0);
            $table->integer('reserved_qty')->unsigned()->default(0);
            $table->integer('quarantine_qty')->unsigned()->default(0);
            $table->decimal('average_cost', 20, 4)->default('0.0000');
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['product_color_id', 'size_value']);
            $table->unique(['sku']);
            $table->index(['product_color_id', 'is_active', 'size_numeric']);
            $table->foreign('product_color_id')->references('id')->on('product_colors')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['code']);
        });

        Schema::create('stock_imports', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('import_code', 50);
            $table->bigInteger('supplier_id')->unsigned();
            $table->string('supplier_code_snapshot', 50);
            $table->string('supplier_name_snapshot', 150);
            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('posted_by')->unsigned()->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->integer('total_quantity')->unsigned()->default(0);
            $table->bigInteger('total_cost_amount')->unsigned()->default(0);
            $table->text('note')->nullable();
            $table->dateTime('posted_at', 6)->nullable();
            $table->char('command_key', 36);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['import_code']);
            $table->unique(['command_key']);
            $table->index(['status', 'posted_at']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('posted_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('stock_import_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('stock_import_id')->unsigned();
            $table->bigInteger('variant_id')->unsigned();
            $table->string('sku_snapshot', 100);
            $table->integer('quantity')->unsigned();
            $table->bigInteger('unit_cost_amount')->unsigned();
            $table->bigInteger('subtotal_amount')->unsigned();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['stock_import_id', 'variant_id']);
            $table->foreign('stock_import_id')->references('id')->on('stock_imports')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('variant_id')->unsigned();
            $table->bigInteger('actor_id')->unsigned()->nullable();
            $table->string('actor_name_snapshot', 100)->nullable();
            $table->string('action', 40);
            $table->integer('stock_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->integer('quarantine_delta')->default(0);
            $table->integer('stock_before')->unsigned();
            $table->integer('stock_after')->unsigned();
            $table->integer('reserved_before')->unsigned();
            $table->integer('reserved_after')->unsigned();
            $table->integer('quarantine_before')->unsigned();
            $table->integer('quarantine_after')->unsigned();
            $table->decimal('average_cost_before', 20, 4);
            $table->decimal('average_cost_after', 20, 4);
            $table->decimal('unit_cost_basis', 20, 4)->nullable();
            $table->string('reference_type', 40);
            $table->bigInteger('reference_id')->unsigned()->nullable();
            $table->string('operation_key', 180);
            $table->string('reason', 500)->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['operation_key']);
            $table->index(['variant_id', 'created_at', 'id']);
            $table->index(['reference_type', 'reference_id']);
            $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('stock_import_items');
        Schema::dropIfExists('stock_imports');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_colors');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
