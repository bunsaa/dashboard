<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uraian_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aktivitas_id')->constrained('aktivitas')->cascadeOnDelete();
            $table->text('uraian_kegiatan');
            $table->string('volume', 100)->nullable();
            $table->decimal('anggaran_rab', 20, 2)->default(0);
            $table->decimal('anggaran_hps', 20, 2)->default(0);
            $table->string('kak_no', 100)->nullable();
            $table->text('kak_spesifikasi')->nullable();
            $table->timestamps();

            $table->index('aktivitas_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uraian_kegiatan');
    }
};
