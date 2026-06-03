<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_stack_settings', function (Blueprint $table) {
            $table->id();
            $table->string('active_stack')->default('nginx_phpfpm');
            $table->string('previous_stack')->nullable();
            $table->integer('nginx_public_port')->default(80);
            $table->integer('apache_backend_port')->default(8080);
            $table->integer('varnish_port')->default(6081);
            $table->string('php_fpm_mode')->default('socket');
            $table->string('php_fpm_socket_path')->default('/run/php-fpm/www.sock');
            $table->timestamp('last_switch_at')->nullable();
            $table->string('last_switch_status')->nullable();
            $table->text('last_validation_output')->nullable();
            $table->timestamps();
        });

        Schema::create('web_stack_history', function (Blueprint $table) {
            $table->id();
            $table->string('from_stack');
            $table->string('to_stack');
            $table->string('status')->default('pending');
            $table->text('validation_output')->nullable();
            $table->text('rollback_output')->nullable();
            $table->string('performed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_stack_history');
        Schema::dropIfExists('web_stack_settings');
    }
};
