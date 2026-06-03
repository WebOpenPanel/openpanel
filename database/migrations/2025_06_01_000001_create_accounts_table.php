<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('domain');
            $table->string('ip_address')->nullable();
            $table->string('email')->nullable();
            $table->string('package')->default('default');
            $table->integer('disk_limit')->default(1000);
            $table->integer('bandwidth_limit')->default(1000);
            $table->string('status')->default('active');
            $table->boolean('backup')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
