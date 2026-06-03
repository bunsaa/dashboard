<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonevUnitKerjaSeeder extends Seeder
{
    /**
     * Sync unit_kerja in the monev database:
     * 1. Fill kode_unit_kerja from renkin.units (matched by nama_unit = nama_unit_kerja)
     * 2. Insert sub-units from contoh.tim_units that don't already exist
     */
    public function run(): void
    {
        $instansiId = 1; // RSUD Tarakan

        // Step 1: Fill kode_unit_kerja from renkin.units
        $renkinUnits = DB::connection('mysql')->table('units')->get();

        foreach ($renkinUnits as $unit) {
            DB::connection('monev')
                ->table('unit_kerja')
                ->where('instansi_id', $instansiId)
                ->where('nama_unit_kerja', $unit->nama_unit)
                ->whereNull('kode_unit_kerja')
                ->update(['kode_unit_kerja' => $unit->kode_unit]);
        }

        $this->command->info('kode_unit_kerja filled from renkin.units');

        // Step 2: Insert tim_units from contoh DB as additional unit_kerja
        $timUnits = DB::connection('mysql')->table('contoh.tim_units')->get();

        $existingNames = DB::connection('monev')
            ->table('unit_kerja')
            ->where('instansi_id', $instansiId)
            ->pluck('nama_unit_kerja')
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->toArray();

        $inserted = 0;

        foreach ($timUnits as $tim) {
            $normalizedName = mb_strtolower(trim($tim->nama_tim));

            if (in_array($normalizedName, $existingNames, true)) {
                continue;
            }

            DB::connection('monev')->table('unit_kerja')->insert([
                'instansi_id'     => $instansiId,
                'kode_unit_kerja' => $tim->kode_unit,
                'nama_unit_kerja' => $tim->nama_tim,
                'nama_atasan'     => null,
                'nip'             => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $existingNames[] = $normalizedName;
            $inserted++;
        }

        $this->command->info("Inserted {$inserted} new unit_kerja from contoh.tim_units");
    }
}
