<?php

namespace App\Exports;

use App\Models\Units;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PegawaiTemplateExport implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private Collection $units;

    public function __construct()
    {
        $this->units = Units::orderBy('kode_unit')->get(['kode_unit', 'nama_unit']);
    }

    public function headings(): array
    {
        return ['Nama', 'NIP', 'Password', 'Peran', 'Status Pegawai', 'Status Kerja', 'Kode Unit'];
    }

    public function array(): array
    {
        return [
            ['Contoh Nama Pegawai', '199001012020011001', 'password', 'staf', 'PNS', 'Aktif', 'Datin'],
        ];
    }

    public function title(): string
    {
        return 'Template';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 25,
            'C' => 15,
            'D' => 15,
            'E' => 35,
            'F' => 15,
            'G' => 20,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        $units = $this->units;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($units) {
                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();

                // Format NIP column (B) as Text to preserve leading zeros
                $sheet->getStyle('B2:B1001')->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_TEXT);

                // Create Master Data sheet
                $masterSheet = $spreadsheet->createSheet();
                $masterSheet->setTitle('Master Data');

                $masterSheet->setCellValue('A1', 'Kode Unit');
                $masterSheet->setCellValue('B1', 'Nama Unit');
                $masterSheet->setCellValue('C1', 'Status Pegawai');
                $masterSheet->setCellValue('D1', 'Status Kerja');
                $masterSheet->setCellValue('E1', 'Peran');

                $masterSheet->getStyle('A1:E1')->applyFromArray(['font' => ['bold' => true]]);
                $masterSheet->getColumnDimension('A')->setWidth(20);
                $masterSheet->getColumnDimension('B')->setWidth(50);
                $masterSheet->getColumnDimension('C')->setWidth(40);
                $masterSheet->getColumnDimension('D')->setWidth(15);
                $masterSheet->getColumnDimension('E')->setWidth(15);

                // Fill unit data
                $unitRow = 2;
                foreach ($units as $unit) {
                    $masterSheet->setCellValue("A{$unitRow}", $unit->kode_unit);
                    $masterSheet->setCellValue("B{$unitRow}", $unit->nama_unit);
                    $unitRow++;
                }
                $unitCount = $units->count();

                // Fill option data
                $statusPegawaiOptions = [
                    'PNS', 'CPNS', 'PPPK', 'PPPK Paruh Waktu',
                    'Pegawai Blud (Tetap Non ASN)', 'PJLP', 'Mitra', 'Pegawai Lainnya Non ASN',
                ];
                foreach ($statusPegawaiOptions as $i => $opt) {
                    $masterSheet->setCellValue('C'.($i + 2), $opt);
                }

                $statusKerjaOptions = ['Aktif', 'Resign', 'Pensiun', 'Mutasi'];
                foreach ($statusKerjaOptions as $i => $opt) {
                    $masterSheet->setCellValue('D'.($i + 2), $opt);
                }

                $peranOptions = ['staf', 'kepala_unit', 'admin_mutu'];
                foreach ($peranOptions as $i => $opt) {
                    $masterSheet->setCellValue('E'.($i + 2), $opt);
                }

                // Add dropdown validations to template sheet (rows 2-1001)
                $statusPegawaiLast = count($statusPegawaiOptions) + 1;
                $statusKerjaLast = count($statusKerjaOptions) + 1;
                $unitLast = $unitCount + 1;

                for ($r = 2; $r <= 1001; $r++) {
                    // Peran (column D)
                    $v = $sheet->getCell("D{$r}")->getDataValidation();
                    $v->setType(DataValidation::TYPE_LIST)
                        ->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setAllowBlank(false)
                        ->setShowDropDown(false)
                        ->setShowErrorMessage(true)
                        ->setErrorTitle('Input tidak valid')
                        ->setError('Pilih peran dari daftar yang tersedia')
                        ->setFormula1("'Master Data'!\$E\$2:\$E\$4");

                    // Status Pegawai (column E)
                    $v = $sheet->getCell("E{$r}")->getDataValidation();
                    $v->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)
                        ->setShowDropDown(false)
                        ->setFormula1("'Master Data'!\$C\$2:\$C\${$statusPegawaiLast}");

                    // Status Kerja (column F)
                    $v = $sheet->getCell("F{$r}")->getDataValidation();
                    $v->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)
                        ->setShowDropDown(false)
                        ->setFormula1("'Master Data'!\$D\$2:\$D\${$statusKerjaLast}");

                    // Kode Unit (column G)
                    $v = $sheet->getCell("G{$r}")->getDataValidation();
                    $v->setType(DataValidation::TYPE_LIST)
                        ->setAllowBlank(true)
                        ->setShowDropDown(false)
                        ->setFormula1("'Master Data'!\$A\$2:\$A\${$unitLast}");
                }

                // Activate template sheet
                $spreadsheet->setActiveSheetIndex(0);
            },
        ];
    }
}
