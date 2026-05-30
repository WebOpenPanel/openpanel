<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain')->unique();
            $table->string('ip_address', 45);
            $table->string('document_root')->nullable();
            $table->string('shell', 50)->default('/bin/bash');
            $table->boolean('shell_access')->default(false);
            $table->bigInteger('disk_usage_bytes')->default(0);
            $table->bigInteger('disk_quota_bytes')->default(0);
            $table->bigInteger('bandwidth_usage_bytes')->default(0);
            $table->bigInteger('bandwidth_limit_bytes')->default(0);
            $table->string('dedicated_ip', 45)->nullable();
            $table->enum('suspended', ['yes', 'no'])->default('no');
            $table->string('suspend_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_accounts');
    }
};
