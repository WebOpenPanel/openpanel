<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ftp_accounts')) {
            return;
        }

        if (!Schema::hasColumn('ftp_accounts', 'account_id')) {
            Schema::table('ftp_accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('user_account_id')->index();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ftp_accounts MODIFY user_account_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ftp_accounts')) {
            return;
        }

        if (Schema::hasColumn('ftp_accounts', 'account_id')) {
            Schema::table('ftp_accounts', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }
    }
};
