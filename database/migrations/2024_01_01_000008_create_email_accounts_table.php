<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('user_accounts')->onDelete('cascade');
            $table->string('domain')->index();
            $table->string('email')->index();
            $table->string('password_hash');
            $table->bigInteger('quota_mb')->default(250);
            $table->bigInteger('used_bytes')->default(0);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('email_forwarders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('user_accounts')->onDelete('cascade');
            $table->string('source_email');
            $table->string('destination_email');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
        });

        Schema::create('email_autoresponders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('user_accounts')->onDelete('cascade');
            $table->string('email')->index();
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_autoresponders');
        Schema::dropIfExists('email_forwarders');
        Schema::dropIfExists('email_accounts');
    }
};
