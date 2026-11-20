<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_delivery_proofs', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('shipment_id')->unsigned();
            $table->bigInteger('uploaded_by')->unsigned();
            $table->string('path', 500);
            $table->char('sha256', 64);
            $table->string('mime_type', 30);
            $table->integer('size_bytes')->unsigned();
            $table->dateTime('created_at', 6);
            $table->unique(['shipment_id']);
            $table->unique(['sha256']);
            $table->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE `payment_events` DROP CHECK `payment_events_provider_allowed_check`');
        DB::statement("ALTER TABLE `payment_events` ADD CONSTRAINT `payment_events_provider_allowed_check` CHECK (CAST(`provider` AS BINARY) IN ('VNPAY', 'VIETQR', 'COD', 'PREPAID_DEMO'))");

        DB::statement("ALTER TABLE `shipment_delivery_proofs` ADD CONSTRAINT `shipment_delivery_proofs_mime_check` CHECK (CAST(`mime_type` AS BINARY) IN ('image/jpeg', 'image/png', 'image/webp'))");
        DB::statement('ALTER TABLE `shipment_delivery_proofs` ADD CONSTRAINT `shipment_delivery_proofs_size_check` CHECK (`size_bytes` BETWEEN 1 AND 5242880)');
        DB::statement('ALTER TABLE `orders` DROP CHECK `orders_payment_method_allowed_check`');
        DB::statement("ALTER TABLE `orders` ADD CONSTRAINT `orders_payment_method_allowed_check` CHECK (CAST(`payment_method` AS BINARY) IN ('COD', 'PREPAID_DEMO', 'VIETQR', 'VNPAY'))");
        DB::statement('ALTER TABLE `payments` DROP CHECK `payments_method_allowed_check`');
        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `payments_method_allowed_check` CHECK (CAST(`method` AS BINARY) IN ('COD', 'PREPAID_DEMO', 'VIETQR', 'VNPAY'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `payment_events` DROP CHECK `payment_events_provider_allowed_check`');
        DB::statement("ALTER TABLE `payment_events` ADD CONSTRAINT `payment_events_provider_allowed_check` CHECK (CAST(`provider` AS BINARY) IN ('VNPAY', 'VIETQR', 'COD'))");
        DB::statement('ALTER TABLE `payments` DROP CHECK `payments_method_allowed_check`');
        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `payments_method_allowed_check` CHECK (CAST(`method` AS BINARY) IN ('COD', 'VIETQR', 'VNPAY'))");
        DB::statement('ALTER TABLE `orders` DROP CHECK `orders_payment_method_allowed_check`');
        DB::statement("ALTER TABLE `orders` ADD CONSTRAINT `orders_payment_method_allowed_check` CHECK (CAST(`payment_method` AS BINARY) IN ('COD', 'VIETQR', 'VNPAY'))");
        DB::statement('ALTER TABLE `shipment_delivery_proofs` DROP CHECK `shipment_delivery_proofs_size_check`');
        DB::statement('ALTER TABLE `shipment_delivery_proofs` DROP CHECK `shipment_delivery_proofs_mime_check`');
        Schema::dropIfExists('shipment_delivery_proofs');
    }
};
