<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('user_accounts')->onDelete('cascade');
            $table->string('domain')->index();
            $table->string('document_root')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->enum('type', ['main', 'addon', 'parked', 'subdomain'])->default('addon');
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_certificate')->nullable();
            $table->string('ssl_key')->nullable();
            $table->string('ssl_ca')->nullable();
            $table->string('ssl_provider')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->boolean('auto_ssl')->default(false);
            $table->text('custom_vhost_config')->nullable();
            $table->string('redirect_url')->nullable();
            $table->enum('redirect_type', ['none', '301', '302'])->default('none');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_account_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
