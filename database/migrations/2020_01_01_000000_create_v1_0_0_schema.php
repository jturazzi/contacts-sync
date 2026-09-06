<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schéma consolidé "v1.0.0" de l'application, remplaçant la série de migrations incrémentales
 * accumulées pendant le développement initial. Reproduit fidèlement l'état actuel de chaque
 * table (colonnes, types, index, clés étrangères) en une seule migration, pour qu'une nouvelle
 * installation parte directement de ce schéma sans avoir à rejouer tout l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('ms_id')->nullable()->unique();
            $table->string('tenant_id')->nullable();
            $table->boolean('sync_all_enabled')->default(false);
            $table->timestamp('last_sync_completed_at')->nullable();
            $table->string('locale', 2)->default('fr');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('avatar')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('microsoft_disconnected_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index(['connection', 'queue', 'failed_at']);
        });

        Schema::create('directory_users', function (Blueprint $table) {
            $table->string('id')->primary(); // Microsoft Graph object id
            $table->string('display_name')->nullable();
            $table->string('given_name')->nullable();
            $table->string('surname')->nullable();
            $table->string('mail')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('office_location')->nullable();
            $table->string('company_name')->nullable();
            $table->boolean('account_enabled')->default(true);
            $table->string('content_hash', 64)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('department');
            $table->index('job_title');
        });

        Schema::create('sync_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('directory_user_id');
            $table->foreign('directory_user_id')->references('id')->on('directory_users')->cascadeOnDelete();
            $table->enum('source', ['manual', 'rule'])->default('manual');
            $table->boolean('enabled')->default(true);
            $table->string('outlook_contact_id')->nullable();
            $table->string('last_hash', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('photo_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'directory_user_id']);
        });

        Schema::create('sync_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('department')->nullable();
            $table->string('job_title')->nullable();
            $table->enum('match_type', ['exact', 'contains'])->default('contains');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('directory_user_id')->nullable();
            $table->foreign('directory_user_id')->references('id')->on('directory_users')->nullOnDelete();
            $table->enum('action', ['created', 'updated', 'deleted', 'error']);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('sync_rules');
        Schema::dropIfExists('sync_selections');
        Schema::dropIfExists('directory_users');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
