<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_kontrak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kontrak_id')->constrained('kontrak')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('progress_kontrak')->nullOnDelete();
            $table->text('uraian_progress')->nullable();
            $table->string('sumber', 20)->default('vendor');
            $table->string('created_by')->nullable();
            $table->string('last_update_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->enum('tipe', ['mingguan', 'bulanan'])->default('mingguan');
            $table->datetime('tanggal_mulai')->nullable();
            $table->datetime('tanggal_akhir')->nullable();
            $table->decimal('persen_rencana', 5, 2)->default(0);
            $table->decimal('persen_realisasi', 5, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->string('file_path')->nullable();
            $table->smallInteger('durasi_hari')->unsigned()->nullable();
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('kabag_comment')->nullable();
            $table->boolean('comment_resolved')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index('kontrak_id');
            $table->index('deleted_at');
            $table->index('status');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_kontrak');
    }
};
