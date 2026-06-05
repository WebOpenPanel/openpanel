<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssl_certificates')) {
            return;
        }

        Schema::table('ssl_certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('ssl_certificates', 'cert_path')) {
                $table->string('cert_path')->nullable()->after('fullchain');
            }
            if (!Schema::hasColumn('ssl_certificates', 'key_path')) {
                $table->string('key_path')->nullable()->after('cert_path');
            }
            if (!Schema::hasColumn('ssl_certificates', 'vhost_installed')) {
                $table->boolean('vhost_installed')->default(false)->after('key_path');
            }
            if (!Schema::hasColumn('ssl_certificates', 'last_renewal_status')) {
                $table->string('last_renewal_status')->nullable()->after('vhost_installed');
            }
            if (!Schema::hasColumn('ssl_certificates', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_renewal_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ssl_certificates')) {
            return;
        }

        Schema::table('ssl_certificates', function (Blueprint $table) {
            foreach (['last_error', 'last_renewal_status', 'vhost_installed', 'key_path', 'cert_path'] as $column) {
                if (Schema::hasColumn('ssl_certificates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
