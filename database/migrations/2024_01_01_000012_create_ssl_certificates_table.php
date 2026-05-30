<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->nullable()->constrained('user_accounts')->nullOnDelete();
            $table->string('domain')->index();
            $table->text('certificate')->nullable();
            $table->text('private_key')->nullable();
            $table->text('ca_bundle')->nullable();
            $table->string('issuer')->nullable();
            $table->string('serial_number')->nullable();
            $table->enum('type', ['self_signed', 'letsencrypt', 'manual', 'purchased'])->default('manual');
            $table->enum('status', ['active', 'expired', 'revoked', 'pending'])->default('pending');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->text('fullchain')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificates');
    }
};
