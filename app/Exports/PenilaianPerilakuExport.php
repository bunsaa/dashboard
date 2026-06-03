<?php

namespace App\Exports;

use App\Models\PenilaianPerilaku;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PenilaianPerilakuExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    protected string $periode;

    public function __construct(string $periode)
    {
        $this->periode = $periode;
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'Status Pegawai',
            'Unit',
            'Berorientasi Pelayanan',
            'Akuntabel',
            'Kompeten',
            'Harmonis',
            'Loyal',
            'Adaptif',
            'Kolaboratif',
            'Nilai Rata-Rata',
            'Keterangan',
            'Status Penilaian',
        ];
    }

    public function array(): array
    {
        $nilaiLabels = [
            'di_atas_ekspektasi' => 'Di Atas Ekspektasi',
            'sesuai_ekspektasi' => 'Sesuai Ekspektasi',
            'di_bawah_ekspektasi' => 'Di Bawah Ekspektasi',
        ];

        $konversi = [
            'di_atas_ekspektasi' => 3,
            'sesuai_ekspektasi' => 2,
            'di_bawah_ekspektasi' => 1,
        ];

        $unsurKeys = ['berorientasi_pelayanan', 'akuntabel', 'kompeten', 'harmonis', 'loyal', 'adaptif', 'kolaboratif'];

        $pegawaiList = User::with('unit')
            ->where('role', 'staf')
            ->orderBy('name', 'asc')
            ->get();

        $penilaianMap = PenilaianPerilaku::where('periode', $this->periode)
            ->whereIn('user_id', $pegawaiList->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $rows = [];
        $no = 1;

        foreach ($pegawaiList as $pegawai) {
            $penilaian = $penilaianMap->get($pegawai->id);

            $rataRata = '-';
            $keterangan = '-';

            if ($penilaian) {
                $totalNilai = 0;
                foreach ($unsurKeys as $key) {
                    $totalNilai += $konversi[$penilaian->{$key}] ?? 0;
                }
                $avg = round($totalNilai / 7, 2);
                $rataRata = $avg;

                if ($avg >= 2.51) {
                    $keterangan = 'Di Atas Ekspektasi';
                } elseif ($avg >= 1.5) {
                    $keterangan = 'Sesuai Ekspektasi';
                } else {
                    $keterangan = 'Di Bawah Ekspektasi';
                }
            }

            $rows[] = [
                $no++,
                $pegawai->name,
                $pegawai->status_pegawai ?? '-',
                $pegawai->unit ? $pegawai->unit->nama_unit : '-',
                $penilaian ? ($nilaiLabels[$penilaian->berorientasi_pelayanan] ?? '-') : '-',
                $penilaian ? ($nilaiLabels[$penilaian->akuntabel] ?? '-') : '-',
                $penilaian ? ($nilaiLabels[$penilaian->kompeten] ?? '-') : '-',
                $penilaian ? ($nilaiLabels[$penilaian->harmonis] ?? '-') : '-',
                $penilaian ? ($nilaiLabels[$penilaian->loyal] ?? '-') : '-',
                $penilaian ? ($nilaiLabels[$penilaian->adaptif] ?? '-') : '-',
                $penilaian ? ($nilaiLabels[$penilaian->kolaboratif] ?? '-') : '-',
                $rataRata,
                $keterangan,
                $penilaian ? 'Selesai' : 'Belum Dinilai',
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Penilaian '.$this->periode;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 25,
            'C' => 20,
            'D' => 25,
            'E' => 22,
            'F' => 22,
            'G' => 22,
            'H' => 22,
            'I' => 22,
            'J' => 22,
            'K' => 22,
            'L' => 16,
            'M' => 22,
            'N' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
