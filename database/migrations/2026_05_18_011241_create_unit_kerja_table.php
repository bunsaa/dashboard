<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansi')->cascadeOnDelete();
            $table->string('kode_unit_kerja')->nullable();
            $table->string('nama_unit_kerja');
            $table->string('nama_atasan')->nullable();
            $table->string('nip', 50)->nullable();
            $table->timestamps();

            $table->index('instansi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerja');
    }
};
