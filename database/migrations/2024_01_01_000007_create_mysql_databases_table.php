<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mysql_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('user_accounts')->onDelete('cascade');
            $table->string('database_name')->index();
            $table->string('charset')->default('utf8mb4');
            $table->string('collation')->default('utf8mb4_unicode_ci');
            $table->bigInteger('size_bytes')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mysql_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('user_accounts')->onDelete('cascade');
            $table->string('username')->index();
            $table->string('password_hash');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mysql_user_database', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mysql_user_id')->constrained('mysql_users')->onDelete('cascade');
            $table->foreignId('mysql_database_id')->constrained('mysql_databases')->onDelete('cascade');
            $table->json('privileges')->nullable();
            $table->timestamps();

            $table->unique(['mysql_user_id', 'mysql_database_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mysql_user_database');
        Schema::dropIfExists('mysql_users');
        Schema::dropIfExists('mysql_databases');
    }
};
