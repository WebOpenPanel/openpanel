<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('service_name')->nullable();
            $table->enum('type', ['systemd', 'init', 'custom'])->default('systemd');
            $table->enum('status', ['running', 'stopped', 'unknown', 'error'])->default('unknown');
            $table->boolean('enabled_on_boot')->default(true);
            $table->boolean('monitor_enabled')->default(false);
            $table->integer('restart_count')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_restarted_at')->nullable();
            $table->text('config_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
