<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_work_items', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 24);
            $table->string('source_event_key', 191)->collation('utf8mb4_bin');
            $table->string('source_reference', 100);
            $table->string('source_label', 120);
            $table->string('title', 200);
            $table->text('content');
            $table->dateTime('occurred_at', 6);
            $table->string('status', 16)->default('PENDING');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['source_type', 'source_event_key'], 'operations_work_items_source_event_unique');
            $table->index(['status', 'occurred_at', 'id'], 'operations_work_items_status_occurred_index');
            $table->index(['source_type', 'source_reference'], 'operations_work_items_source_reference_index');
        });
        DB::statement("ALTER TABLE `operations_work_items` ADD CONSTRAINT `operations_work_items_source_type_check` CHECK (CAST(`source_type` AS BINARY) IN ('ORDER', 'SHIPMENT', 'INVENTORY', 'AFTER_SALES', 'PAYMENT', 'OTHER'))");
        DB::statement("ALTER TABLE `operations_work_items` ADD CONSTRAINT `operations_work_items_status_check` CHECK (CAST(`status` AS BINARY) IN ('PENDING', 'IN_PROGRESS', 'COMPLETED'))");
        DB::statement('ALTER TABLE `operations_work_items` ADD CONSTRAINT `operations_work_items_version_check` CHECK (`version` >= 1)');

        Schema::create('operations_work_item_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_item_id')->constrained('operations_work_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name_snapshot', 100);
            $table->json('actor_roles_snapshot');
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('outcome_code', 64)->nullable();
            $table->text('note')->nullable();
            $table->json('snapshot');
            $table->dateTime('created_at', 6);
            $table->index(['work_item_id', 'created_at', 'id'], 'operations_work_item_histories_item_created_index');
        });
        DB::statement("ALTER TABLE `operations_work_item_histories` ADD CONSTRAINT `operations_work_item_histories_status_check` CHECK ((`from_status` IS NULL OR CAST(`from_status` AS BINARY) IN ('PENDING', 'IN_PROGRESS', 'COMPLETED')) AND CAST(`to_status` AS BINARY) IN ('PENDING', 'IN_PROGRESS', 'COMPLETED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_work_item_histories');
        Schema::dropIfExists('operations_work_items');
    }
};
