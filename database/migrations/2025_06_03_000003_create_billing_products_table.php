<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_products', function (Blueprint $table) {
            $table->id();
            $table->string('external_product_id')->nullable();
            $table->string('product_name');
            $table->unsignedBigInteger('package_id')->nullable();
            $table->enum('account_type', ['shared', 'wordpress', 'reseller'])->default('shared');
            $table->string('default_web_stack')->default('nginx_phpfpm');
            $table->boolean('auto_install_wordpress')->default(false);
            $table->string('wordpress_profile')->default('safe_default');
            $table->boolean('redis_enabled')->default(false);
            $table->boolean('varnish_enabled')->default(false);
            $table->boolean('backups_enabled')->default(true);
            $table->boolean('staging_enabled')->default(false);
            $table->integer('max_sites')->default(1);
            $table->integer('max_storage')->default(1000);
            $table->integer('max_bandwidth')->default(10000);
            $table->integer('max_databases')->default(5);
            $table->integer('max_email_accounts')->default(10);
            $table->integer('max_ftp_accounts')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_products');
    }
};
