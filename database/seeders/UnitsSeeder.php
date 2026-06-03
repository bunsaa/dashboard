<?php

namespace Database\Seeders;

use App\Models\Units;
use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            // Komite-Komite
            ['kode_unit' => 'EtikHukum', 'nama_unit' => 'Komite Etik dan Hukum', 'alias' => 'EtikHukum'],
            ['kode_unit' => 'EtikPenelitian', 'nama_unit' => 'Komite Etik Penelitian', 'alias' => 'EtikPenelitian'],
            ['kode_unit' => 'Komdik', 'nama_unit' => 'Komite Medik', 'alias' => 'Komdik'],
            ['kode_unit' => 'PRA', 'nama_unit' => 'Komite PRA', 'alias' => 'PRA'],
            ['kode_unit' => 'KRM', 'nama_unit' => 'Komite Rekam Medik', 'alias' => 'KRM'],
            ['kode_unit' => 'Komkoridk', 'nama_unit' => 'Komkordik', 'alias' => 'Komkordik'],
            ['kode_unit' => 'Keperawatan', 'nama_unit' => 'Komite Keperawatan', 'alias' => 'Keperawatan'],
            ['kode_unit' => 'Mutu', 'nama_unit' => 'Komite Mutu dan Keselamatan Pasien', 'alias' => 'Mutu'],
            ['kode_unit' => 'PPI', 'nama_unit' => 'Komite PPI', 'alias' => 'PPI'],
            ['kode_unit' => 'K3KL', 'nama_unit' => 'Komite Profesi Kesehatan Lain', 'alias' => 'K3KL'],
            ['kode_unit' => 'FarmasiTerapi', 'nama_unit' => 'Komite Farmasi & Terapi', 'alias' => 'FarmasiTerapi'],

            // Dewan Pengawas
            ['kode_unit' => 'DEWAS', 'nama_unit' => 'Dewan Pengawas', 'alias' => 'DEWAS'],

            // Satuan Pengawas Internal
            ['kode_unit' => 'SPI', 'nama_unit' => 'Satuan Pengawas Internal', 'alias' => 'SPI'],

            // Wakil Direktur
            ['kode_unit' => 'WADIR_PEL', 'nama_unit' => 'Wakil Direktur Pelayanan', 'alias' => 'WADIR_PEL'],
            ['kode_unit' => 'WADIR_AdminKum', 'nama_unit' => 'Wakil Direktur Administrasi Umum dan Keuangan', 'alias' => 'WADIR_AdminKum'],

            // Bidang Pelayanan
            ['kode_unit' => 'Medik', 'nama_unit' => 'Bidang Pelayanan Medik', 'alias' => 'Medik'],
            ['kode_unit' => 'Penunjang', 'nama_unit' => 'Bidang Pelayanan Penunjang', 'alias' => 'Penunjang'],
            ['kode_unit' => 'Layanan_Keperawatan', 'nama_unit' => 'Bidang Pelayanan Keperawatan', 'alias' => 'layanan_Keperawatan'],

            // Bagian-Bagian
            ['kode_unit' => 'BSDM', 'nama_unit' => 'Bagian SDM, Pendidikan dan Penelitian', 'alias' => 'BSDM'],
            ['kode_unit' => 'Datin', 'nama_unit' => 'Bagian Data dan Teknologi Informasi', 'alias' => 'Datin'],
            ['kode_unit' => 'Umum_Pemasaran', 'nama_unit' => 'Bagian Umum dan Pemasaran', 'alias' => 'Umum_Pemasaran'],
            ['kode_unit' => 'Keu', 'nama_unit' => 'Bagian Keuangan dan Perencanaan', 'alias' => 'Keu'],

            // Kelompok Jabatan Fungsional
            ['kode_unit' => 'KJF', 'nama_unit' => 'Kelompok Jabatan Fungsional', 'alias' => 'KJF'],
        ];

        foreach ($units as $unit) {
            Units::updateOrCreate(
                ['kode_unit' => $unit['kode_unit']],
                ['nama_unit' => $unit['nama_unit'], 'alias' => $unit['alias']],
            );
        }
    }
}
