<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->integer('disk_space_mb')->default(1024);
            $table->integer('bandwidth_mb')->default(10240);
            $table->integer('max_domains')->default(1);
            $table->integer('max_subdomains')->default(5);
            $table->integer('max_email_accounts')->default(10);
            $table->integer('max_databases')->default(5);
            $table->integer('max_ftp_accounts')->default(5);
            $table->integer('max_parked_domains')->default(0);
            $table->boolean('shell_access')->default(false);
            $table->boolean('dedicated_ip')->default(false);
            $table->string('php_version')->nullable();
            $table->string('web_server')->default('apache')->nullable();
            $table->boolean('ssl_enabled')->default(true);
            $table->integer('max_cron_jobs')->default(5);
            $table->integer('max_email_lists')->default(0);
            $table->integer('hourly_emails')->default(50);
            $table->integer('max_addon_domains')->default(0);
            $table->string('reseller')->nullable()->default('');
            $table->integer('max_accounts')->default(0);
            $table->string('cgroups')->default('');
            $table->integer('nproc')->default(40);
            $table->integer('apache_nproc')->default(40);
            $table->integer('inode')->default(0);
            $table->integer('nofile')->default(200);
            $table->integer('nodejs_apps')->default(1);
            $table->integer('mongo_database')->default(0);
            $table->integer('pgresql_database')->default(0);
            $table->integer('tomcat_apps')->default(0);
            $table->text('cron_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
