<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classifications', function (Blueprint $table): void {
            $table->boolean('show_in_home_nav')->default(0)->after('is_active');
            $table->index(['classification_group', 'is_active', 'show_in_home_nav', 'sort_order'], 'classifications_home_nav_idx');
        });

        DB::statement('ALTER TABLE `classifications` ADD CONSTRAINT `classifications_show_in_home_nav_boolean_check` CHECK (`show_in_home_nav` IN (0, 1))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `classifications` DROP CHECK `classifications_show_in_home_nav_boolean_check`');

        Schema::table('classifications', function (Blueprint $table): void {
            $table->dropIndex('classifications_home_nav_idx');
            $table->dropColumn('show_in_home_nav');
        });
    }
};
