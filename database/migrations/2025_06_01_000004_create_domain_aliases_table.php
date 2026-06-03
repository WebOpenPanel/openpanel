<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('user_accounts')->onDelete('cascade');
            $table->string('domain');
            $table->string('alias')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active');
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_aliases');
    }
};
