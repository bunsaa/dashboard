<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\MonevVendor;
use App\Models\Monev\UnitKerja;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'admin_mutu';
        $vendorQuery = MonevVendor::with(['kontrak.progress' => function ($q) {
            $q->whereNull('parent_id')->whereNotNull('tanggal_akhir')->where('sumber', 'vendor');
        }, 'kontrak' => function ($q) {
            $q->whereNotNull('tanggal_akhir');
        }])->oldest();

        if (! $isAdmin) {
            $unitKerja = $user->kode_unit ? UnitKerja::where('kode_unit_kerja', $user->kode_unit)->first() : null;
            if ($unitKerja) {
                $vendorQuery->whereHas('kontrak.uraianKegiatan.aktivitas',
                    fn ($q) => $q->where('unit_kerja_id', $unitKerja->id)
                );
            }
        }

        $vendors = $vendorQuery->get();

        $result = $vendors->map(function ($vendor) {
            $stats = collect();

            foreach ($vendor->kontrak as $k) {
                foreach ($k->progress as $p) {
                    if ($p->status !== 'approved') {
                        continue;
                    }

                    $stats->push([
                        'late' => $p->tanggal_akhir > $k->tanggal_akhir,
                    ]);
                }
            }

            $total = $stats->count();
            $lateCount = $stats->filter(fn ($s) => $s['late'])->count();

            if ($total === 0) {
                $penilaian = ['status' => 'nodata', 'label' => 'Belum Ada Data', 'note' => 'Belum ada progress yang disetujui'];
            } elseif ($lateCount === 0) {
                $penilaian = ['status' => 'good', 'label' => 'Baik', 'note' => "Direkomendasikan — semua {$total} progress tepat waktu & disetujui"];
            } elseif ($lateCount <= 3) {
                $penilaian = ['status' => 'consider', 'label' => 'Dipertimbangkan', 'note' => "Total {$lateCount}x keterlambatan (maks 3x) — semua progress disetujui"];
            } else {
                $penilaian = ['status' => 'bad', 'label' => 'Tidak Direkomendasikan', 'note' => "Total {$lateCount}x keterlambatan melebihi batas yang dapat diterima"];
            }

            return array_merge($vendor->toArray(), [
                'total_kontrak' => $vendor->kontrak->count(),
                'penilaian' => $penilaian,
            ]);
        });

        return Inertia::render('Monev/Vendor', [
            'vendor' => $result,
        ]);
    }

    public function export(): StreamedResponse
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'admin_mutu';

        $vendorQuery = MonevVendor::with(['kontrak.progress' => function ($q) {
            $q->whereNull('parent_id')->whereNotNull('tanggal_akhir')->where('sumber', 'vendor');
        }, 'kontrak' => function ($q) {
            $q->whereNotNull('tanggal_akhir');
        }])->oldest();

        if (! $isAdmin) {
            $unitKerja = $user->kode_unit ? UnitKerja::where('kode_unit_kerja', $user->kode_unit)->first() : null;
            if ($unitKerja) {
                $vendorQuery->whereHas('kontrak.uraianKegiatan.aktivitas',
                    fn ($q) => $q->where('unit_kerja_id', $unitKerja->id)
                );
            }
        }

        $vendors = $vendorQuery->get()->map(function ($vendor) {
            $stats = collect();
            foreach ($vendor->kontrak as $k) {
                foreach ($k->progress as $p) {
                    if ($p->status !== 'approved') {
                        continue;
                    }
                    $stats->push(['late' => $p->tanggal_akhir > $k->tanggal_akhir]);
                }
            }
            $total = $stats->count();
            $lateCount = $stats->filter(fn ($s) => $s['late'])->count();

            if ($total === 0) {
                $penilaian = 'Belum Ada Data';
            } elseif ($lateCount === 0) {
                $penilaian = 'Baik';
            } elseif ($lateCount <= 3) {
                $penilaian = 'Dipertimbangkan';
            } else {
                $penilaian = 'Tidak Direkomendasikan';
            }

            $nama = $vendor->jenis_vendor === 'Pribadi'
                ? $vendor->nama_vendor
                : trim("{$vendor->jenis_vendor} {$vendor->nama_vendor}");

            return [
                'nama' => $nama,
                'jenis' => $vendor->jenis_vendor,
                'direktur' => $vendor->direktur ?? '-',
                'no_hp' => $vendor->no_hp ?? '-',
                'total_kontrak' => $vendor->kontrak->count(),
                'penilaian' => $penilaian,
                'total_terlambat' => $lateCount,
                'total_progress' => $total,
            ];
        });

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vendor');

        $headers = ['No', 'Nama Vendor', 'Jenis', 'Direktur / Penanggung Jawab', 'No HP', 'Total Kontrak', 'Total Progress', 'Terlambat', 'Penilaian'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
        }

        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        foreach ($vendors as $ri => $v) {
            $row = $ri + 2;
            $sheet->setCellValueByColumnAndRow(1, $row, $ri + 1);
            $sheet->setCellValueByColumnAndRow(2, $row, $v['nama']);
            $sheet->setCellValueByColumnAndRow(3, $row, $v['jenis']);
            $sheet->setCellValueByColumnAndRow(4, $row, $v['direktur']);
            $sheet->setCellValueByColumnAndRow(5, $row, $v['no_hp']);
            $sheet->setCellValueByColumnAndRow(6, $row, $v['total_kontrak']);
            $sheet->setCellValueByColumnAndRow(7, $row, $v['total_progress']);
            $sheet->setCellValueByColumnAndRow(8, $row, $v['total_terlambat']);
            $sheet->setCellValueByColumnAndRow(9, $row, $v['penilaian']);
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getStyle('A2:I'.(count($vendors) + 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $filename = 'vendor-monev-'.now()->format('Y-m-d').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
