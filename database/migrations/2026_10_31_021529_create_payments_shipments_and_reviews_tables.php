<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('origin_payment_id')->unsigned()->nullable();
            $table->string('attempt_ref', 100);
            $table->string('method', 20);
            $table->string('status', 20)->default('PENDING');
            $table->string('disposition', 30)->default('NONE');
            $table->bigInteger('expected_amount')->unsigned();
            $table->bigInteger('received_amount')->unsigned()->default(0);
            $table->bigInteger('applied_amount')->unsigned()->default(0);
            $table->char('currency', 3)->default('VND');
            $table->string('provider_transaction_id', 150)->nullable();
            $table->string('bank_transfer_content', 255)->nullable();
            $table->string('customer_reported_reference', 150)->nullable();
            $table->string('customer_reported_evidence_path', 500)->nullable();
            $table->dateTime('customer_reported_at', 6)->nullable();
            $table->bigInteger('confirmed_by')->unsigned()->nullable();
            $table->string('confirmation_evidence_path', 500)->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->dateTime('succeeded_at', 6)->nullable();
            $table->string('idempotency_key', 180);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['attempt_ref']);
            $table->unique(['idempotency_key']);
            $table->unique(['method', 'provider_transaction_id']);
            $table->index(['order_id', 'status']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('origin_payment_id')->references('id')->on('payments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('confirmed_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('payment_id')->unsigned()->nullable();
            $table->string('provider', 30);
            $table->char('event_key', 64);
            $table->string('provider_event_ref', 150)->nullable();
            $table->boolean('signature_valid');
            $table->string('processing_status', 30);
            $table->json('payload_redacted');
            $table->string('response_code', 20)->nullable();
            $table->dateTime('received_at', 6);
            $table->dateTime('processed_at', 6)->nullable();
            $table->unique(['event_key']);
            $table->index(['payment_id', 'received_at']);
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('order_id')->unsigned();
            $table->string('tracking_code', 100)->nullable();
            $table->string('carrier_name', 100);
            $table->string('status', 30)->default('READY');
            $table->bigInteger('created_by')->unsigned();
            $table->dateTime('shipped_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->dateTime('failed_at', 6)->nullable();
            $table->dateTime('return_received_at', 6)->nullable();
            $table->string('proof_path', 500)->nullable();
            $table->string('note', 1000)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['order_id']);
            $table->index(['status', 'shipped_at']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('shipment_return_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('shipment_id')->unsigned();
            $table->bigInteger('order_item_unit_id')->unsigned();
            $table->bigInteger('variant_id')->unsigned();
            $table->bigInteger('received_by')->unsigned();
            $table->dateTime('received_at', 6);
            $table->string('disposition', 20)->default('PENDING');
            $table->decimal('cost_basis', 20, 4);
            $table->bigInteger('inspected_by')->unsigned()->nullable();
            $table->dateTime('inspected_at', 6)->nullable();
            $table->string('note', 1000);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['order_item_unit_id']);
            $table->index(['shipment_id', 'disposition']);
            $table->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('order_item_unit_id')->references('id')->on('order_item_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('received_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('inspected_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('order_item_unit_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->decimal('rating', 2, 1);
            $table->text('comment');
            $table->text('admin_reply')->nullable();
            $table->boolean('is_visible')->default(1);
            $table->bigInteger('replied_by')->unsigned()->nullable();
            $table->dateTime('replied_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['order_item_unit_id']);
            $table->index(['user_id', 'created_at']);
            $table->foreign('order_item_unit_id')->references('id')->on('order_item_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('replied_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('shipment_return_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
