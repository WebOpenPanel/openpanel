<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mysql_databases')) {
            Schema::table('mysql_databases', function (Blueprint $table) {
                if (!Schema::hasColumn('mysql_databases', 'account_id')) {
                    $table->unsignedBigInteger('account_id')->nullable()->index()->after('id');
                }
            });

            if (DB::getDriverName() === 'mysql' && Schema::hasColumn('mysql_databases', 'user_account_id')) {
                DB::statement('ALTER TABLE mysql_databases MODIFY user_account_id BIGINT UNSIGNED NULL');
            }
        }

        if (Schema::hasTable('mysql_users')) {
            Schema::table('mysql_users', function (Blueprint $table) {
                if (!Schema::hasColumn('mysql_users', 'account_id')) {
                    $table->unsignedBigInteger('account_id')->nullable()->index()->after('id');
                }
            });

            if (DB::getDriverName() === 'mysql' && Schema::hasColumn('mysql_users', 'user_account_id')) {
                DB::statement('ALTER TABLE mysql_users MODIFY user_account_id BIGINT UNSIGNED NULL');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mysql_users') && Schema::hasColumn('mysql_users', 'account_id')) {
            Schema::table('mysql_users', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }

        if (Schema::hasTable('mysql_databases') && Schema::hasColumn('mysql_databases', 'account_id')) {
            Schema::table('mysql_databases', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }
    }
};
