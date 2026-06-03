<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anggaran_pengadaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->foreignId('instansi_id')->nullable()->constrained('instansi')->nullOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->double('nominal')->default(0);
            $table->unsignedTinyInteger('edit_count')->default(0);
            $table->json('edit_history')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['unit_kerja_id', 'tahun']);
            $table->index(['instansi_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggaran_pengadaan');
    }
};
