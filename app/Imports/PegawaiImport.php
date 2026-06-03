<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PegawaiImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var array<string> */
    public array $failures = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // +2: 0-based index, row 1 is heading

            $rawNip = $row['nip'] ?? null;
            $nip = null;
            if ($rawNip !== null && $rawNip !== '') {
                // Handle numeric values (e.g., Excel reads NIP as float)
                if (is_float($rawNip) || is_int($rawNip)) {
                    $nip = number_format((float) $rawNip, 0, '.', '');
                } else {
                    $nip = trim((string) $rawNip);
                }
                $nip = $nip ?: null;
            }

            $rawPassword = $row['password'] ?? null;
            $password = ($rawPassword !== null && trim((string) $rawPassword) !== '')
                ? trim((string) $rawPassword)
                : 'password';

            $data = [
                'name' => $row['nama'] ? trim((string) $row['nama']) : null,
                'nip' => $nip,
                'password' => $password,
                'role' => $row['peran'] ? trim((string) $row['peran']) : null,
                'status_pegawai' => ($row['status_pegawai'] ?? '') !== '' ? trim((string) $row['status_pegawai']) : null,
                'status_kerja' => ($row['status_kerja'] ?? '') !== '' ? trim((string) $row['status_kerja']) : null,
                'kode_unit' => ($row['kode_unit'] ?? '') !== '' ? trim((string) $row['kode_unit']) : null,
            ];

            $validator = Validator::make($data, [
                'name' => 'required|string|max:255',
                'nip' => 'nullable|string|max:30|unique:users,nip',
                'password' => 'required|string|min:6',
                'role' => 'required|in:admin_mutu,kepala_unit,staf',
                'status_pegawai' => 'nullable|in:PNS,CPNS,PPPK,PPPK Paruh Waktu,Pegawai Blud (Tetap Non ASN),PJLP,Mitra,Pegawai Lainnya Non ASN',
                'status_kerja' => 'nullable|in:Aktif,Resign,Pensiun,Mutasi',
                'kode_unit' => 'nullable|exists:units,kode_unit',
            ]);

            if ($validator->fails()) {
                $errors = implode(', ', $validator->errors()->all());
                $this->failures[] = "Baris {$rowNum}: {$errors}";

                continue;
            }

            $email = $data['nip'] ? $data['nip'].'@rsud.local' : uniqid().'@rsud.local';

            User::create([
                'name' => $data['name'],
                'email' => $email,
                'nip' => $data['nip'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status_pegawai' => $data['status_pegawai'],
                'status_kerja' => $data['status_kerja'],
                'kode_unit' => $data['kode_unit'],
            ]);

            $this->imported++;
        }
    }
}
