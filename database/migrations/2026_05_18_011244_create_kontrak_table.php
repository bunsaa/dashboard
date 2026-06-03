<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontrak', function (Blueprint $table) {
            $table->id();
            $table->string('no_kontrak');
            $table->date('tanggal_kontrak')->nullable();
            $table->text('uraian_pekerjaan')->nullable();
            $table->decimal('nominal_kontrak', 20, 2)->default(0);
            $table->foreignId('uraian_kegiatan_id')->constrained('uraian_kegiatan')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('pelaksana')->nullable();
            $table->string('no_hp_pelaksana', 12)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->string('dokumen_kontrak_path')->nullable();
            $table->timestamps();

            $table->index('uraian_kegiatan_id');
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kontrak');
    }
};
