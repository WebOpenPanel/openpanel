<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->enum('action', ['allow', 'deny', 'reject', 'drop']);
            $table->enum('protocol', ['tcp', 'udp', 'icmp', 'all'])->default('tcp');
            $table->string('source_ip', 45)->nullable();
            $table->string('source_port')->nullable();
            $table->string('destination_ip', 45)->nullable();
            $table->string('destination_port')->nullable();
            $table->string('direction', 20)->default('in');
            $table->integer('priority')->default(0);
            $table->text('comment')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->text('reason')->nullable();
            $table->string('added_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('allowed_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->text('description')->nullable();
            $table->string('added_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowed_ips');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('firewall_rules');
    }
};
