<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('after_sales_requests', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('request_code', 50);
            $table->bigInteger('order_item_unit_id')->unsigned();
            $table->string('type', 30);
            $table->string('status', 30)->default('NEW');
            $table->bigInteger('source_variant_id')->unsigned();
            $table->bigInteger('target_variant_id')->unsigned()->nullable();
            $table->boolean('target_reservation_active')->default(0);
            $table->boolean('source_in_quarantine')->default(0);
            $table->string('return_disposition', 20)->default('PENDING');
            $table->bigInteger('disposition_by')->unsigned()->nullable();
            $table->dateTime('disposition_at', 6)->nullable();
            $table->text('customer_reason');
            $table->text('staff_note')->nullable();
            $table->bigInteger('handled_by')->unsigned()->nullable();
            $table->dateTime('receive_by_at', 6)->nullable();
            $table->dateTime('received_at', 6)->nullable();
            $table->dateTime('accepted_at', 6)->nullable();
            $table->dateTime('ready_at', 6)->nullable();
            $table->dateTime('customer_handover_at', 6)->nullable();
            $table->bigInteger('returned_cogs_amount')->unsigned()->nullable();
            $table->bigInteger('replacement_cogs_amount')->unsigned()->nullable();
            $table->bigInteger('repair_expense_amount')->unsigned()->default(0);
            $table->bigInteger('repair_expense_approved_by')->unsigned()->nullable();
            $table->dateTime('repair_expense_approved_at', 6)->nullable();
            $table->dateTime('resolved_at', 6)->nullable();
            $table->string('command_key', 180);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['request_code']);
            $table->unique(['command_key']);
            $table->index(['status', 'receive_by_at']);
            $table->foreign('order_item_unit_id')->references('id')->on('order_item_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('source_variant_id')->references('id')->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('target_variant_id')->references('id')->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('disposition_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('handled_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('repair_expense_approved_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('after_sales_histories', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('after_sales_request_id')->unsigned();
            $table->bigInteger('actor_id')->unsigned()->nullable();
            $table->string('actor_name_snapshot', 100);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('event_type', 40);
            $table->string('visibility', 20)->default('CUSTOMER');
            $table->string('note', 2000)->nullable();
            $table->string('command_key', 180);
            $table->dateTime('created_at', 6);
            $table->unique(['command_key']);
            $table->index(['after_sales_request_id', 'created_at', 'id']);
            $table->foreign('after_sales_request_id')->references('id')->on('after_sales_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('after_sales_attachments', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('after_sales_request_id')->unsigned();
            $table->bigInteger('uploaded_by')->unsigned();
            $table->string('disk', 20)->default('private');
            $table->string('path', 500);
            $table->string('mime_type', 100);
            $table->integer('size_bytes')->unsigned();
            $table->char('sha256', 64);
            $table->dateTime('created_at', 6);
            $table->unique(['path']);
            $table->index(['after_sales_request_id', 'created_at']);
            $table->foreign('after_sales_request_id')->references('id')->on('after_sales_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('payment_id')->unsigned();
            $table->bigInteger('after_sales_request_id')->unsigned()->nullable();
            $table->bigInteger('order_item_unit_id')->unsigned()->nullable();
            $table->string('reason_type', 30);
            $table->string('status', 20)->default('REQUESTED');
            $table->bigInteger('merchandise_amount')->unsigned()->default(0);
            $table->bigInteger('shipping_amount')->unsigned()->default(0);
            $table->bigInteger('amount')->unsigned();
            $table->string('operation_key', 180);
            $table->string('destination_type', 20);
            $table->text('destination_ciphertext')->nullable();
            $table->bigInteger('requested_by')->unsigned()->nullable();
            $table->bigInteger('approved_by')->unsigned()->nullable();
            $table->bigInteger('paid_by')->unsigned()->nullable();
            $table->string('external_reference', 150)->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->dateTime('succeeded_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['operation_key']);
            $table->unique(['external_reference']);
            $table->index(['payment_id', 'status']);
            $table->index(['order_item_unit_id', 'status']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('after_sales_request_id')->references('id')->on('after_sales_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('order_item_unit_id')->references('id')->on('order_item_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('paid_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('sales_ledger_entries', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('order_id')->unsigned()->nullable();
            $table->bigInteger('order_item_unit_id')->unsigned()->nullable();
            $table->bigInteger('after_sales_request_id')->unsigned()->nullable();
            $table->bigInteger('refund_id')->unsigned()->nullable();
            $table->bigInteger('inventory_movement_id')->unsigned()->nullable();
            $table->string('type', 40);
            $table->bigInteger('merchandise_revenue_delta')->default(0);
            $table->bigInteger('shipping_revenue_delta')->default(0);
            $table->bigInteger('cogs_delta')->default(0);
            $table->bigInteger('expense_delta')->default(0);
            $table->string('operation_key', 180);
            $table->dateTime('occurred_at', 6);
            $table->dateTime('created_at', 6);
            $table->unique(['operation_key']);
            $table->index(['occurred_at', 'type']);
            $table->index(['order_id', 'order_item_unit_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('order_item_unit_id')->references('id')->on('order_item_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('after_sales_request_id')->references('id')->on('after_sales_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('refund_id')->references('id')->on('refunds')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('inventory_movement_id')->references('id')->on('inventory_movements')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('event_type', 80);
            $table->string('aggregate_type', 40);
            $table->bigInteger('aggregate_id')->unsigned();
            $table->string('dedup_key', 180);
            $table->json('payload');
            $table->string('status', 20)->default('PENDING');
            $table->smallInteger('attempts')->unsigned()->default(0);
            $table->dateTime('available_at', 6);
            $table->dateTime('lease_until', 6)->nullable();
            $table->dateTime('sent_at', 6)->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['dedup_key']);
            $table->index(['status', 'available_at']);
            $table->index(['lease_until']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('sales_ledger_entries');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('after_sales_attachments');
        Schema::dropIfExists('after_sales_histories');
        Schema::dropIfExists('after_sales_requests');
    }
};
