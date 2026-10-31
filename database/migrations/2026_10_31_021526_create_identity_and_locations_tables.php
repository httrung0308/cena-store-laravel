<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('full_name', 100);
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->string('password', 255);
            $table->string('avatar_path', 500)->nullable();
            $table->boolean('is_active')->default(1);
            $table->dateTime('last_login_at', 6)->nullable();
            $table->dateTime('email_verified_at', 6)->nullable();
            $table->integer('auth_version')->unsigned()->default(1);
            $table->string('remember_token', 100)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['email']);
            $table->index(['is_active', 'created_at']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 30);
            $table->string('description', 255)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['slug']);
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('role_id')->unsigned();
            $table->bigInteger('assigned_by')->unsigned()->nullable();
            $table->dateTime('assigned_at', 6);
            $table->unique(['user_id', 'role_id']);
            $table->index(['role_id', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('assigned_by')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('password_reset_challenges', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->char('challenge_token', 64);
            $table->string('otp_hash', 255);
            $table->tinyInteger('attempts')->unsigned()->default(0);
            $table->dateTime('expires_at', 6);
            $table->dateTime('last_sent_at', 6);
            $table->dateTime('verified_at', 6)->nullable();
            $table->dateTime('used_at', 6)->nullable();
            $table->dateTime('invalidated_at', 6)->nullable();
            $table->char('session_binding_hash', 64);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['user_id']);
            $table->unique(['challenge_token']);
            $table->index(['expires_at']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('administrative_units', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('level', 20);
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->string('dataset_version', 50);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['dataset_version', 'code']);
            $table->index(['dataset_version', 'level', 'parent_id', 'is_active'], 'administrative_units_index_2');
            $table->foreign('parent_id')->references('id')->on('administrative_units')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('addresses', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->bigInteger('province_id')->unsigned();
            $table->bigInteger('ward_id')->unsigned();
            $table->string('province_code_snapshot', 20);
            $table->string('province_name_snapshot', 150);
            $table->string('ward_code_snapshot', 20);
            $table->string('ward_name_snapshot', 150);
            $table->string('dataset_version', 50);
            $table->string('street_address', 255);
            $table->string('legacy_district_text', 150)->nullable();
            $table->boolean('is_default')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->index(['user_id', 'is_default']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('province_id')->references('id')->on('administrative_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('ward_id')->references('id')->on('administrative_units')->restrictOnDelete()->restrictOnUpdate();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('administrative_units');
        Schema::dropIfExists('password_reset_challenges');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
