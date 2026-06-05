<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wordpress_sites')) {
            return;
        }

        Schema::table('wordpress_sites', function (Blueprint $table) {
            if (!Schema::hasColumn('wordpress_sites', 'parent_site_id')) {
                $table->unsignedBigInteger('parent_site_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('wordpress_sites', 'site_type')) {
                $table->string('site_type')->default('live')->after('parent_site_id')->index();
            }
            if (!Schema::hasColumn('wordpress_sites', 'last_pushed_at')) {
                $table->timestamp('last_pushed_at')->nullable()->after('last_backup_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('wordpress_sites')) {
            return;
        }

        Schema::table('wordpress_sites', function (Blueprint $table) {
            if (Schema::hasColumn('wordpress_sites', 'last_pushed_at')) {
                $table->dropColumn('last_pushed_at');
            }
            if (Schema::hasColumn('wordpress_sites', 'site_type')) {
                $table->dropColumn('site_type');
            }
            if (Schema::hasColumn('wordpress_sites', 'parent_site_id')) {
                $table->dropColumn('parent_site_id');
            }
        });
    }
};
