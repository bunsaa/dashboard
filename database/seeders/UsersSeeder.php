<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Akun dummy untuk login (NIP + password):
     * ┌──────────────────────────────────────────┬──────────────┬─────────────┬──────────────┐
     * │ Nama                                     │ NIP          │ Password    │ Role         │
     * ├──────────────────────────────────────────┼──────────────┼─────────────┼──────────────┤
     * │ Test User (existing)                     │ (admin_mutu) │ password    │ admin_mutu   │
     * │ Kepala Unit Demo                         │ 2000000001   │ Demo@123    │ kepala_unit  │
     * │ Staf Demo                                │ 3000000001   │ Demo@123    │ staf         │
     * │ (23 kepala unit, satu per unit organisasi)│ 40000000XX   │ password    │ kepala_unit  │
     * └──────────────────────────────────────────┴──────────────┴─────────────┴──────────────┘
     */
    public function run(): void
    {
        // Gunakan team yang sudah ada (team pertama)
        $team = Team::first();

        if (! $team) {
            return;
        }

        // Pastikan user admin utama (test@example.com) adalah admin_mutu dengan NIP + password yang benar
        User::where('email', 'test@example.com')->update([
            'role' => 'admin_mutu',
            'nip' => '1000000001',
            'password' => Hash::make('Admin@123'),
            'email_verified_at' => now(),
        ]);

        // Akun dummy Kepala Unit (untuk testing login manual)
        $kepalaUnitDemo = User::updateOrCreate(
            ['email' => 'kepalaunit@mutu.rsud.go.id'],
            [
                'name' => 'Kepala Unit Demo',
                'nip' => '2000000001',
                'password' => Hash::make('Demo@123'),
                'role' => 'kepala_unit',
                'kode_unit' => 'Datin',
                'status_pegawai' => 'PNS',
                'current_team_id' => $team->id,
                'email_verified_at' => now(),
            ],
        );
        $team->members()->syncWithoutDetaching([$kepalaUnitDemo->id => ['role' => TeamRole::Member->value]]);

        // Akun dummy Staf (untuk testing login manual)
        $stafDemo = User::updateOrCreate(
            ['email' => 'staf@mutu.rsud.go.id'],
            [
                'name' => 'Staf Demo',
                'nip' => '3000000001',
                'password' => Hash::make('Demo@123'),
                'role' => 'staf',
                'kode_unit' => 'Datin',
                'status_pegawai' => 'PNS',
                'current_team_id' => $team->id,
                'email_verified_at' => now(),
            ],
        );
        $team->members()->syncWithoutDetaching([$stafDemo->id => ['role' => TeamRole::Member->value]]);

        // Kepala Unit per unit organisasi (NIP: 40000000XX)
        $unitUsers = [
            ['name' => 'Komite Etik dan Hukum',                          'email' => 'etikhukum@mutu.rsud.go.id',            'nip' => '4000000001', 'kode_unit' => 'EtikHukum'],
            ['name' => 'Komite Etik Penelitian',                         'email' => 'etikpenelitian@mutu.rsud.go.id',        'nip' => '4000000002', 'kode_unit' => 'EtikPenelitian'],
            ['name' => 'Komite Medik',                                   'email' => 'komdik@mutu.rsud.go.id',                'nip' => '4000000003', 'kode_unit' => 'Komdik'],
            ['name' => 'Komite PRA',                                     'email' => 'pra@mutu.rsud.go.id',                   'nip' => '4000000004', 'kode_unit' => 'PRA'],
            ['name' => 'Komite Rekam Medik',                             'email' => 'krm@mutu.rsud.go.id',                   'nip' => '4000000005', 'kode_unit' => 'KRM'],
            ['name' => 'Komkordik',                                      'email' => 'komkordik@mutu.rsud.go.id',             'nip' => '4000000006', 'kode_unit' => 'Komkoridk'],
            ['name' => 'Komite Keperawatan',                             'email' => 'keperawatan@mutu.rsud.go.id',           'nip' => '4000000007', 'kode_unit' => 'Keperawatan'],
            ['name' => 'Komite Mutu dan Keselamatan Pasien',             'email' => 'mutu@mutu.rsud.go.id',                  'nip' => '4000000008', 'kode_unit' => 'Mutu'],
            ['name' => 'Komite PPI',                                     'email' => 'ppi@mutu.rsud.go.id',                   'nip' => '4000000009', 'kode_unit' => 'PPI'],
            ['name' => 'Komite Profesi Kesehatan Lain',                  'email' => 'k3kl@mutu.rsud.go.id',                  'nip' => '4000000010', 'kode_unit' => 'K3KL'],
            ['name' => 'Komite Farmasi & Terapi',                        'email' => 'farmasiterapi@mutu.rsud.go.id',         'nip' => '4000000011', 'kode_unit' => 'FarmasiTerapi'],
            ['name' => 'Dewan Pengawas',                                 'email' => 'dewas@mutu.rsud.go.id',                 'nip' => '4000000012', 'kode_unit' => 'DEWAS'],
            ['name' => 'Satuan Pengawas Internal',                       'email' => 'spi@mutu.rsud.go.id',                   'nip' => '4000000013', 'kode_unit' => 'SPI'],
            ['name' => 'Wakil Direktur Pelayanan',                       'email' => 'wadir.pel@mutu.rsud.go.id',             'nip' => '4000000014', 'kode_unit' => 'WADIR_PEL'],
            ['name' => 'Wakil Direktur Administrasi Umum dan Keuangan',  'email' => 'wadir.adminkum@mutu.rsud.go.id',        'nip' => '4000000015', 'kode_unit' => 'WADIR_AdminKum'],
            ['name' => 'Bidang Pelayanan Medik',                         'email' => 'medik@mutu.rsud.go.id',                 'nip' => '4000000016', 'kode_unit' => 'Medik'],
            ['name' => 'Bidang Pelayanan Penunjang',                     'email' => 'penunjang@mutu.rsud.go.id',             'nip' => '4000000017', 'kode_unit' => 'Penunjang'],
            ['name' => 'Bidang Pelayanan Keperawatan',                   'email' => 'layanan.keperawatan@mutu.rsud.go.id',   'nip' => '4000000018', 'kode_unit' => 'Layanan_Keperawatan'],
            ['name' => 'Bagian SDM, Pendidikan dan Penelitian',          'email' => 'bsdm@mutu.rsud.go.id',                  'nip' => '4000000019', 'kode_unit' => 'BSDM'],
            ['name' => 'Bagian Data dan Teknologi Informasi',            'email' => 'datin@mutu.rsud.go.id',                 'nip' => '4000000020', 'kode_unit' => 'Datin'],
            ['name' => 'Bagian Umum dan Pemasaran',                      'email' => 'umum.pemasaran@mutu.rsud.go.id',        'nip' => '4000000021', 'kode_unit' => 'Umum_Pemasaran'],
            ['name' => 'Bagian Keuangan dan Perencanaan',                'email' => 'keu@mutu.rsud.go.id',                   'nip' => '4000000022', 'kode_unit' => 'Keu'],
            ['name' => 'Kelompok Jabatan Fungsional',                    'email' => 'kjf@mutu.rsud.go.id',                   'nip' => '4000000023', 'kode_unit' => 'KJF'],
        ];

        foreach ($unitUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'nip' => $data['nip'],
                    'password' => Hash::make('password'),
                    'role' => 'kepala_unit',
                    'kode_unit' => $data['kode_unit'],
                    'current_team_id' => $team->id,
                    'email_verified_at' => now(),
                ],
            );

            $team->members()->syncWithoutDetaching([$user->id => ['role' => TeamRole::Member->value]]);
        }
    }
}
