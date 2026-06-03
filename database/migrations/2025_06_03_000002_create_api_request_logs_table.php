<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('ip', 45);
            $table->integer('status_code');
            $table->float('duration_ms')->nullable();
            $table->string('action')->nullable();
            $table->text('params_summary')->nullable(); // no secrets
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
