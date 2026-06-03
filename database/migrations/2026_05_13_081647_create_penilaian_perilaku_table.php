<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('penilaian_perilaku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('penilai_id')->constrained('users')->onDelete('cascade');
            $table->string('kode_unit');
            $table->string('periode');
            $table->string('berorientasi_pelayanan')->nullable();
            $table->string('akuntabel')->nullable();
            $table->string('kompeten')->nullable();
            $table->string('harmonis')->nullable();
            $table->string('loyal')->nullable();
            $table->string('adaptif')->nullable();
            $table->string('kolaboratif')->nullable();
            $table->string('status')->default('belum_dinilai');
            $table->timestamps();

            $table->unique(['user_id', 'periode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penilaian_perilaku');
    }
};
