<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_domains')) {
            Schema::create('email_domains', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('domain')->unique();
                $table->string('status')->default('active')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_accounts')) {
            return;
        }

        Schema::table('email_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('email_accounts', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('email_accounts', 'domain_id')) {
                $table->unsignedBigInteger('domain_id')->nullable()->index()->after('account_id');
            }
            if (!Schema::hasColumn('email_accounts', 'local_part')) {
                $table->string('local_part')->nullable()->index()->after('domain');
            }
            if (!Schema::hasColumn('email_accounts', 'mailbox_path')) {
                $table->string('mailbox_path')->nullable()->after('quota_mb');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE email_accounts MODIFY user_account_id BIGINT UNSIGNED NULL');
            DB::statement("ALTER TABLE email_accounts MODIFY status ENUM('active','suspended','disabled') DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_accounts')) {
            Schema::table('email_accounts', function (Blueprint $table) {
                foreach (['mailbox_path', 'local_part', 'domain_id', 'account_id'] as $column) {
                    if (Schema::hasColumn('email_accounts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('email_domains');
    }
};
