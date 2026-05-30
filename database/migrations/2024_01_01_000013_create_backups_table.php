<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->nullable()->constrained('user_accounts')->nullOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->bigInteger('size_bytes')->default(0);
            $table->enum('type', ['full', 'incremental', 'account', 'database', 'files']);
            $table->enum('status', ['pending', 'running', 'completed', 'failed']);
            $table->enum('destination', ['local', 'remote', 's3', 'ftp']);
            $table->string('remote_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('backup_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('frequency')->default('daily');
            $table->integer('retention_days')->default(30);
            $table->json('accounts')->nullable();
            $table->boolean('include_databases')->default(true);
            $table->boolean('include_email')->default(true);
            $table->boolean('include_files')->default(true);
            $table->string('destination')->default('local');
            $table->string('remote_host')->nullable();
            $table->string('remote_user')->nullable();
            $table->string('remote_path')->nullable();
            $table->string('remote_port')->default('22');
            $table->text('notification_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_configs');
        Schema::dropIfExists('backups');
    }
};
