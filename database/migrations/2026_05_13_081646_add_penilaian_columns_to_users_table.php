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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('staf')->after('email');
            }
            if (! Schema::hasColumn('users', 'kode_unit')) {
                $table->string('kode_unit')->nullable()->after('role');
            }
            if (! Schema::hasColumn('users', 'status_pegawai')) {
                $table->string('status_pegawai')->nullable()->after('kode_unit');
            }
            if (! Schema::hasColumn('users', 'penilaian_aktif')) {
                $table->boolean('penilaian_aktif')->default(false)->after('status_pegawai');
            }
        });

        // Add FK only if not exists (cross-database compatible)
        $hasFk = collect(Schema::getForeignKeys('users'))
            ->contains(fn ($fk) => in_array('kode_unit', $fk['columns']));

        if (! $hasFk) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('kode_unit')->references('kode_unit')->on('units')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['kode_unit']);
            $table->dropColumn(['role', 'kode_unit', 'status_pegawai', 'penilaian_aktif']);
        });
    }
};
