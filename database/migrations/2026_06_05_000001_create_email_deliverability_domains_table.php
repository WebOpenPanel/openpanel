<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_deliverability_domains')) {
            return;
        }

        Schema::create('email_deliverability_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('domain');
            $table->string('selector')->default('default');
            $table->boolean('dkim_enabled')->default(false);
            $table->text('dkim_public_record')->nullable();
            $table->string('dkim_private_key_path')->nullable();
            $table->text('spf_record')->nullable();
            $table->text('dmarc_record')->nullable();
            $table->text('mx_record')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'selector']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliverability_domains');
    }
};
