<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->char('guest_token_hash', 64)->nullable();
            $table->integer('version')->unsigned()->default(1);
            $table->dateTime('expires_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['user_id']);
            $table->unique(['guest_token_hash']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('cart_id')->unsigned();
            $table->bigInteger('variant_id')->unsigned();
            $table->integer('quantity')->unsigned()->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['cart_id', 'variant_id']);
            $table->foreign('cart_id')->references('id')->on('carts')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('type', 30);
            $table->integer('value')->unsigned();
            $table->bigInteger('min_order_amount')->unsigned()->default(0);
            $table->bigInteger('max_discount_amount')->unsigned()->nullable();
            $table->integer('usage_limit')->unsigned()->nullable();
            $table->integer('per_user_limit')->unsigned()->default(1);
            $table->dateTime('starts_at', 6)->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['code']);
            $table->index(['is_active', 'starts_at', 'expires_at']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('order_code', 50);
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('coupon_id')->unsigned()->nullable();
            $table->string('coupon_code_snapshot', 50)->nullable();
            $table->bigInteger('subtotal_amount')->unsigned();
            $table->bigInteger('merchandise_discount_amount')->unsigned()->default(0);
            $table->bigInteger('shipping_base_fee')->unsigned();
            $table->bigInteger('shipping_discount_amount')->unsigned()->default(0);
            $table->bigInteger('shipping_fee')->unsigned();
            $table->bigInteger('total_amount')->unsigned();
            $table->string('order_status', 30)->default('PENDING');
            $table->string('payment_method', 20);
            $table->string('payment_status', 30)->default('UNPAID');
            $table->bigInteger('paid_amount')->unsigned()->default(0);
            $table->bigInteger('refunded_amount')->unsigned()->default(0);
            $table->string('shipping_name', 100);
            $table->string('shipping_phone', 20);
            $table->json('shipping_address_snapshot');
            $table->string('customer_note', 1000)->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->char('checkout_key', 36);
            $table->char('checkout_fingerprint', 64);
            $table->integer('source_cart_version')->unsigned();
            $table->dateTime('payment_expires_at', 6)->nullable();
            $table->dateTime('confirmed_at', 6)->nullable();
            $table->dateTime('packed_at', 6)->nullable();
            $table->dateTime('shipped_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->dateTime('failed_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['order_code']);
            $table->unique(['user_id', 'checkout_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['order_status', 'payment_expires_at']);
            $table->index(['delivered_at']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('coupon_id')->references('id')->on('coupons')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('variant_id')->unsigned();
            $table->string('product_name_snapshot', 150);
            $table->string('style_color_snapshot', 50);
            $table->string('color_name_snapshot', 100);
            $table->string('size_value_snapshot', 10);
            $table->string('sku_snapshot', 100);
            $table->string('thumbnail_snapshot', 1000)->nullable();
            $table->tinyInteger('quantity')->unsigned();
            $table->bigInteger('original_unit_price')->unsigned();
            $table->bigInteger('unit_price')->unsigned();
            $table->bigInteger('subtotal_amount')->unsigned();
            $table->bigInteger('discount_amount')->unsigned()->default(0);
            $table->bigInteger('net_amount')->unsigned();
            $table->smallInteger('exchange_days_snapshot')->unsigned();
            $table->smallInteger('warranty_months_snapshot')->unsigned();
            $table->decimal('cost_unit_snapshot', 20, 4)->nullable();
            $table->bigInteger('cogs_amount')->unsigned()->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['order_id', 'variant_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('order_item_units', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('order_item_id')->unsigned();
            $table->tinyInteger('unit_no')->unsigned();
            $table->bigInteger('current_variant_id')->unsigned();
            $table->string('state', 30)->default('ALLOCATED');
            $table->bigInteger('gross_amount')->unsigned();
            $table->bigInteger('discount_amount')->unsigned()->default(0);
            $table->bigInteger('net_amount')->unsigned();
            $table->bigInteger('original_cogs_amount')->unsigned()->nullable();
            $table->bigInteger('current_cogs_amount')->unsigned()->nullable();
            $table->tinyInteger('exchange_completed_count')->unsigned()->default(0);
            $table->dateTime('exchange_deadline_at', 6)->nullable();
            $table->dateTime('warranty_deadline_at', 6)->nullable();
            $table->bigInteger('refunded_merchandise_amount')->unsigned()->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['order_item_id', 'unit_no']);
            $table->index(['current_variant_id', 'state']);
            $table->foreign('order_item_id')->references('id')->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('current_variant_id')->references('id')->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('actor_id')->unsigned()->nullable();
            $table->string('actor_name_snapshot', 100);
            $table->json('actor_roles_snapshot')->nullable();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason_code', 50);
            $table->string('note', 1000)->nullable();
            $table->string('command_key', 180);
            $table->dateTime('created_at', 6);
            $table->unique(['command_key']);
            $table->index(['order_id', 'created_at', 'id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('coupon_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('order_id')->unsigned();
            $table->string('status', 20)->default('RESERVED');
            $table->bigInteger('merchandise_discount_amount')->unsigned()->default(0);
            $table->bigInteger('shipping_discount_amount')->unsigned()->default(0);
            $table->dateTime('reserved_at', 6);
            $table->dateTime('consumed_at', 6)->nullable();
            $table->dateTime('released_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['order_id']);
            $table->index(['coupon_id', 'status', 'user_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_item_units');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
