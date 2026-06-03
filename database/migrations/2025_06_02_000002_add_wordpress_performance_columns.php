<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_sites', function (Blueprint $table) {
            // Phase 1: Redis per-site DB index
            $table->tinyInteger('redis_db_index')->default(0)->after('redis_prefix');

            // Phase 3: Performance profile
            $table->string('performance_profile')->default('safe_default')->after('status');

            // Phase 4: PHP-FPM tuning
            $table->string('php_fpm_pm')->default('ondemand')->after('performance_profile');
            $table->integer('php_fpm_max_children')->default(10)->after('php_fpm_pm');
            $table->integer('php_fpm_memory_limit')->default(256)->after('php_fpm_max_children');
            $table->integer('php_fpm_max_execution_time')->default(60)->after('php_fpm_memory_limit');
            $table->integer('php_fpm_upload_max_filesize')->default(64)->after('php_fpm_max_execution_time');

            // Phase 6: WP-Cron control
            $table->boolean('wp_cron_disabled')->default(false)->after('php_fpm_upload_max_filesize');
            $table->integer('wp_cron_interval')->default(0)->after('wp_cron_disabled'); // 0=use WP default
        });
    }

    public function down(): void
    {
        Schema::table('wordpress_sites', function (Blueprint $table) {
            $table->dropColumn([
                'redis_db_index',
                'performance_profile',
                'php_fpm_pm',
                'php_fpm_max_children',
                'php_fpm_memory_limit',
                'php_fpm_max_execution_time',
                'php_fpm_upload_max_filesize',
                'wp_cron_disabled',
                'wp_cron_interval',
            ]);
        });
    }
};
