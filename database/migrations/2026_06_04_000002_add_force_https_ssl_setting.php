<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ssl_certificates') && !Schema::hasColumn('ssl_certificates', 'force_https')) {
            Schema::table('ssl_certificates', function (Blueprint $table) {
                $table->boolean('force_https')->default(false)->after('auto_renew');
            });
        }

        if (Schema::hasTable('domains') && !Schema::hasColumn('domains', 'force_https')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->boolean('force_https')->default(false)->after('auto_ssl');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ssl_certificates') && Schema::hasColumn('ssl_certificates', 'force_https')) {
            Schema::table('ssl_certificates', function (Blueprint $table) {
                $table->dropColumn('force_https');
            });
        }

        if (Schema::hasTable('domains') && Schema::hasColumn('domains', 'force_https')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropColumn('force_https');
            });
        }
    }
};
