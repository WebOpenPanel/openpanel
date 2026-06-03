<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_account_id')->nullable()->index();
            $table->unsignedBigInteger('domain_id')->nullable()->index();
            $table->string('domain')->index();
            $table->string('install_path');
            $table->string('site_url');
            $table->string('admin_user')->default('admin');
            $table->string('admin_email')->nullable();
            $table->string('db_name');
            $table->string('db_user');
            $table->text('db_password_encrypted');
            $table->string('wp_version')->nullable();
            $table->string('php_version')->default('8.2');
            $table->string('stack_name')->default('nginx_phpfpm');
            $table->boolean('redis_enabled')->default(false);
            $table->string('redis_prefix')->nullable();
            $table->boolean('varnish_enabled')->default(false);
            $table->boolean('ssl_enabled')->default(false);
            $table->string('status')->default('active')->index();
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamp('last_backup_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wordpress_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wordpress_site_id')->nullable()->index();
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->longText('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('wordpress_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wordpress_site_id')->constrained('wordpress_sites')->onDelete('cascade');
            $table->string('backup_path');
            $table->string('backup_type')->default('full');
            $table->bigInteger('size_bytes')->default(0);
            $table->string('status')->default('completed');
            $table->timestamps();
        });

        Schema::create('wordpress_security_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wordpress_site_id')->constrained('wordpress_sites')->onDelete('cascade');
            $table->string('wp_version')->nullable();
            $table->boolean('outdated_core')->default(false);
            $table->integer('outdated_plugins')->default(0);
            $table->integer('outdated_themes')->default(0);
            $table->integer('suspicious_files')->default(0);
            $table->integer('weak_permissions')->default(0);
            $table->json('result_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_security_scans');
        Schema::dropIfExists('wordpress_backups');
        Schema::dropIfExists('wordpress_tasks');
        Schema::dropIfExists('wordpress_sites');
    }
};
