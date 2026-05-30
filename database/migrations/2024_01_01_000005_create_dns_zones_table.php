<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->nullable()->constrained('user_accounts')->nullOnDelete();
            $table->string('domain')->unique();
            $table->string('nameserver1')->nullable();
            $table->string('nameserver2')->nullable();
            $table->string('nameserver1_ip', 45)->nullable();
            $table->string('nameserver2_ip', 45)->nullable();
            $table->integer('ttl')->default(14400);
            $table->integer('serial')->default(0);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_zones');
    }
};
