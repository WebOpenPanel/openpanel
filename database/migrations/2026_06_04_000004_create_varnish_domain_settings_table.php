<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('varnish_domain_settings')) {
            Schema::create('varnish_domain_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('domain')->unique();
                $table->boolean('varnish_enabled')->default(true);
                $table->enum('varnish_mode', ['bypass', 'shield', 'cache'])->default('shield');
                $table->enum('static_asset_mode', ['nginx_direct', 'varnish_cached'])->default('nginx_direct');
                $table->integer('html_ttl')->default(0);
                $table->integer('static_ttl')->default(86400);
                $table->integer('grace_ttl')->default(3600);
                $table->boolean('purge_enabled')->default(true);
                $table->timestamp('last_purged_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('accounts')) {
            $accounts = DB::table('accounts')->select('id', 'domain')->get();
            foreach ($accounts as $account) {
                $site = Schema::hasTable('wordpress_sites')
                    ? DB::table('wordpress_sites')->where('domain', $account->domain)->whereNull('deleted_at')->first()
                    : null;

                $isWpCached = $site && !empty($site->varnish_enabled);
                DB::table('varnish_domain_settings')->updateOrInsert(
                    ['domain' => $account->domain],
                    [
                        'account_id' => $account->id,
                        'varnish_enabled' => true,
                        'varnish_mode' => $isWpCached ? 'cache' : 'shield',
                        'static_asset_mode' => 'nginx_direct',
                        'html_ttl' => $isWpCached ? 300 : 0,
                        'static_ttl' => 86400,
                        'grace_ttl' => 3600,
                        'purge_enabled' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('varnish_domain_settings');
    }
};
