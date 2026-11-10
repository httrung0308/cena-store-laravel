<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_return_events', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('shipment_id')->unsigned();
            $table->bigInteger('shipment_return_item_id')->unsigned()->nullable();
            $table->bigInteger('order_item_unit_id')->unsigned()->nullable();
            $table->string('event_type', 30);
            $table->bigInteger('actor_id')->unsigned();
            $table->string('note', 1000)->nullable();
            $table->dateTime('occurred_at', 6);
            $table->dateTime('created_at', 6);
            $table->unique(['shipment_id', 'order_item_unit_id', 'event_type'], 'shipment_return_events_shipment_unit_type_unique');
            $table->index(['shipment_id', 'occurred_at', 'id'], 'shipment_return_events_shipment_time_index');
            $table->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('shipment_return_item_id')->references('id')->on('shipment_return_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('order_item_unit_id')->references('id')->on('order_item_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE `shipment_return_events` ADD CONSTRAINT `shipment_return_events_type_allowed_check` CHECK (CAST(`event_type` AS BINARY) IN ('RECEIVED', 'RESTOCKED', 'WRITE_OFF_PROPOSED', 'WRITE_OFF_APPROVED', 'MISSING_UNIT', 'SKU_MISMATCH', 'DAMAGED_UNIT', 'NO_EVIDENCE'))");
        DB::statement("ALTER TABLE `shipment_return_events` ADD CONSTRAINT `shipment_return_events_source_shape_check` CHECK ((`event_type` IN ('RECEIVED', 'RESTOCKED', 'WRITE_OFF_PROPOSED', 'WRITE_OFF_APPROVED') AND `shipment_return_item_id` IS NOT NULL AND `order_item_unit_id` IS NOT NULL) OR (`event_type` IN ('MISSING_UNIT', 'SKU_MISMATCH', 'DAMAGED_UNIT', 'NO_EVIDENCE') AND `shipment_return_item_id` IS NULL AND `order_item_unit_id` IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_return_events');
    }
};
