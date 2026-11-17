<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('order_status_history_id')->unsigned();
            $table->string('notification_key', 180);
            $table->string('order_status', 30);
            $table->dateTime('read_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['order_status_history_id'], 'notifications_status_history_unique');
            $table->unique(['notification_key'], 'notifications_key_unique');
            $table->index(['user_id', 'read_at', 'created_at', 'id'], 'notifications_user_read_time_index');
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('order_status_history_id')->references('id')->on('order_status_histories')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement("ALTER TABLE `notifications` ADD CONSTRAINT `notifications_status_allowed_check` CHECK (CAST(`order_status` AS BINARY) IN ('PACKING', 'ON_DELIVERY', 'DELIVERED', 'CANCELLED', 'FAILED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
