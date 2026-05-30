<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR']);
            $table->text('value');
            $table->integer('ttl')->default(14400);
            $table->integer('priority')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
