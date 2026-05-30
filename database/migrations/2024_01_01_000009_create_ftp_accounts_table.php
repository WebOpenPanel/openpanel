<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('user_accounts')->onDelete('cascade');
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->string('home_directory');
            $table->bigInteger('quota_mb')->default(0);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ftp_accounts');
    }
};
