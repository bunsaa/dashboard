<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\Aktivitas;
use App\Models\Monev\Instansi;
use App\Models\Monev\Kontrak;
use App\Models\Monev\MonevVendor;
use App\Models\Monev\UnitKerja;
use App\Models\Monev\UraianKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KontrakController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $isFullAdmin = $user->role === 'admin_mutu';
        $isKepalaUnit = $user->role === 'kepala_unit';
        $isAdmin = $isFullAdmin || $isKepalaUnit;

        if ($isFullAdmin) {
            $instansiId    = $request->get('instansi_id') ?: 1;
            $unitKerjaId   = $request->get('unit_kerja_id');
            $unitKerjaName = null;
        } else {
            // kepala_unit & staf: resolve unit_kerja_id dari kode_unit user
            $instansiId    = null;
            $unitKerja     = $user->kode_unit ? UnitKerja::where('kode_unit_kerja', $user->kode_unit)->first() : null;
            $unitKerjaId   = $unitKerja?->id;
            $unitKerjaName = $unitKerja?->nama_unit_kerja;
        }

        $kontrakQuery = Kontrak::with(['vendor', 'uraianKegiatan.aktivitas.unitKerja.instansi'])->oldest();

        if ($unitKerjaId) {
            $kontrakQuery->whereHas('uraianKegiatan.aktivitas', fn ($q) => $q->where('unit_kerja_id', $unitKerjaId));
        } elseif ($instansiId) {
            $kontrakQuery->whereHas('uraianKegiatan.aktivitas', function ($q) use ($instansiId) {
                $q->where(function ($qq) use ($instansiId) {
                    $qq->whereHas('unitKerja', fn ($qqq) => $qqq->where('instansi_id', $instansiId))
                        ->orWhereNull('unit_kerja_id');
                });
            });
        } else {
            $kontrakQuery->whereRaw('0=1');
        }

        $kontrak = $kontrakQuery->get()->map(fn ($k) => array_merge($k->toArray(), [
            'dokumen_url' => $k->dokumen_kontrak_path
                ? Storage::disk('public')->url($k->dokumen_kontrak_path)
                : null,
            'dokumen_name' => $k->dokumen_kontrak_path
                ? basename($k->dokumen_kontrak_path)
                : null,
        ]));

        $uraianQuery = UraianKegiatan::with('aktivitas')->oldest();
        if ($unitKerjaId) {
            $uraianQuery->whereHas('aktivitas', fn ($q) => $q->where('unit_kerja_id', $unitKerjaId));
        } elseif ($instansiId) {
            $uraianQuery->whereHas('aktivitas', function ($q) use ($instansiId) {
                $q->where(function ($qq) use ($instansiId) {
                    $qq->whereHas('unitKerja', fn ($qqq) => $qqq->where('instansi_id', $instansiId))
                        ->orWhereNull('unit_kerja_id');
                });
            });
        } else {
            $uraianQuery->whereRaw('0=1');
        }

        $unitKerjaList = $instansiId
            ? UnitKerja::where('instansi_id', $instansiId)->orderBy('nama_unit_kerja')->get(['id', 'nama_unit_kerja'])
            : collect();

        return Inertia::render('Monev/Kontrak', [
            'kontrak' => $kontrak,
            'uraianKegiatan' => $uraianQuery->get(),
            'vendors' => MonevVendor::select('id', 'jenis_vendor', 'nama_vendor', 'direktur', 'no_hp')->oldest()->get(),
            'instansiList' => $isFullAdmin ? Instansi::orderBy('nama_instansi')->get(['id', 'nama_instansi']) : collect(),
            'unitKerjaList' => $unitKerjaList,
            'selectedInstansi' => $instansiId ? (int) $instansiId : null,
            'selectedUnit' => $unitKerjaId ? (int) $unitKerjaId : null,
            'selectedUnitName' => $unitKerjaName ?? null,
            'isAdmin' => $isAdmin,
            'canEdit' => $isFullAdmin,
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin_mutu') {
            abort(403);
        }

        $validated = $request->validate([
            'no_kontrak' => 'required|string|max:100',
            'tanggal_kontrak' => 'nullable|date',
            'uraian_pekerjaan' => 'nullable|string',
            'nominal_kontrak' => 'nullable|numeric|min:0',
            'uraian_kegiatan_id' => ['nullable', Rule::exists(UraianKegiatan::class, 'id')],
            'jenis_vendor' => 'nullable|in:PT,CV,Pribadi',
            'nama_vendor' => 'nullable|string|max:255',
            'direktur' => 'nullable|string|max:255',
            'no_hp' => 'nullable|string|max:20',
            'pelaksana' => 'nullable|string|max:255',
            'no_hp_pelaksana' => 'nullable|string|min:9|max:12',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_akhir' => 'nullable|date|after_or_equal:tanggal_mulai',
            'dokumen' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        $validated['vendor_id'] = $this->resolveVendor($validated);
        $validated['nominal_kontrak'] = $validated['nominal_kontrak'] ?? 0;

        if ($request->hasFile('dokumen')) {
            $validated['dokumen_kontrak_path'] = $request->file('dokumen')->store('dokumen-kontrak', 'public');
        }

        unset($validated['dokumen'], $validated['jenis_vendor'], $validated['nama_vendor'],
            $validated['direktur'], $validated['no_hp']);
        Kontrak::create($validated);

        return redirect()->back()->with('success', 'Kontrak berhasil ditambahkan.');
    }

    public function update(Request $request, Kontrak $kontrak)
    {
        if (auth()->user()->role !== 'admin_mutu') {
            abort(403);
        }

        $validated = $request->validate([
            'no_kontrak' => 'required|string|max:100',
            'tanggal_kontrak' => 'nullable|date',
            'uraian_pekerjaan' => 'nullable|string',
            'nominal_kontrak' => 'nullable|numeric|min:0',
            'uraian_kegiatan_id' => ['nullable', Rule::exists(UraianKegiatan::class, 'id')],
            'jenis_vendor' => 'nullable|in:PT,CV,Pribadi',
            'nama_vendor' => 'nullable|string|max:255',
            'direktur' => 'nullable|string|max:255',
            'no_hp' => 'nullable|string|max:20',
            'pelaksana' => 'nullable|string|max:255',
            'no_hp_pelaksana' => 'nullable|string|min:9|max:12',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_akhir' => 'nullable|date|after_or_equal:tanggal_mulai',
            'dokumen' => 'nullable|file|mimes:pdf|max:5120',
            'remove_dokumen' => 'nullable|boolean',
        ]);

        $validated['vendor_id'] = $this->resolveVendor($validated);
        $validated['nominal_kontrak'] = $validated['nominal_kontrak'] ?? 0;

        $filePath = $kontrak->dokumen_kontrak_path;

        if ($request->boolean('remove_dokumen')) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = null;
        }

        if ($request->hasFile('dokumen')) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $request->file('dokumen')->store('dokumen-kontrak', 'public');
        }

        unset($validated['dokumen'], $validated['remove_dokumen'],
            $validated['jenis_vendor'], $validated['nama_vendor'],
            $validated['direktur'], $validated['no_hp']);
        $validated['dokumen_kontrak_path'] = $filePath;

        $kontrak->update($validated);

        return redirect()->back()->with('success', 'Kontrak berhasil diperbarui.');
    }

    public function destroy(Kontrak $kontrak)
    {
        if (auth()->user()->role !== 'admin_mutu') {
            abort(403);
        }

        $kontrak->delete();

        return redirect()->back()->with('success', 'Kontrak berhasil dihapus.');
    }

    public function export()
    {
        $user = auth()->user();
        $isFullAdmin = $user->role === 'admin_mutu';
        $year = now()->year;
        $query = Aktivitas::with(['uraianKegiatan.kontrak.vendor', 'uraianKegiatan.kontrak.progress'])->oldest();

        if (! $isFullAdmin) {
            $unitKerja = $user->kode_unit ? UnitKerja::where('kode_unit_kerja', $user->kode_unit)->first() : null;
            if ($unitKerja) {
                $query->where('unit_kerja_id', $unitKerja->id);
            } else {
                $query->whereRaw('0=1');
            }
        }

        $aktivitas = $query->get();
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriks Kontrak');

        $sheet->setCellValue('A1', "MATRIKS KEGIATAN TAHUN {$year}");
        $sheet->mergeCells('A1:S1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
        ]);
        $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue('A2', 'No');
        $sheet->mergeCells('A2:A4');
        $sheet->setCellValue('B2', 'Kegiatan dan Uraian');
        $sheet->mergeCells('B2:B4');
        $sheet->setCellValue('C2', 'Volume');
        $sheet->mergeCells('C2:D2');
        $sheet->setCellValue('E2', 'Anggaran');
        $sheet->mergeCells('E2:F2');
        $sheet->setCellValue('G2', 'KAK');
        $sheet->mergeCells('G2:H2');
        $sheet->setCellValue('I2', 'Kontrak');
        $sheet->mergeCells('I2:Q2');
        $sheet->setCellValue('R2', 'Progress');
        $sheet->mergeCells('R2:R4');
        $sheet->setCellValue('S2', 'Keterangan');
        $sheet->mergeCells('S2:S4');
        $sheet->setCellValue('C3', 'Vol.');
        $sheet->mergeCells('C3:C4');
        $sheet->setCellValue('D3', 'Satuan');
        $sheet->mergeCells('D3:D4');
        $sheet->setCellValue('E3', 'RAB');
        $sheet->mergeCells('E3:E4');
        $sheet->setCellValue('F3', 'HPS');
        $sheet->mergeCells('F3:F4');
        $sheet->setCellValue('G3', 'No');
        $sheet->mergeCells('G3:G4');
        $sheet->setCellValue('H3', 'Spesifikasi');
        $sheet->mergeCells('H3:H4');
        $sheet->setCellValue('I3', 'No Kontrak');
        $sheet->mergeCells('I3:I4');
        $sheet->setCellValue('J3', 'Tgl Kontrak');
        $sheet->mergeCells('J3:J4');
        $sheet->setCellValue('K3', 'Nilai');
        $sheet->mergeCells('K3:K4');
        $sheet->setCellValue('L3', 'Persentase');
        $sheet->mergeCells('L3:L4');
        $sheet->setCellValue('M3', 'Waktu Pelaksanaan');
        $sheet->mergeCells('M3:N3');
        $sheet->setCellValue('O3', 'Vendor');
        $sheet->mergeCells('O3:Q3');
        $sheet->setCellValue('M4', 'Tanggal Mulai');
        $sheet->setCellValue('N4', 'Tanggal Selesai');
        $sheet->setCellValue('O4', 'Nama Vendor');
        $sheet->setCellValue('P4', 'Direktur');
        $sheet->setCellValue('Q4', 'Pelaksana');

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD6E4F0']],
        ];
        $sheet->getStyle('A2:S4')->applyFromArray($headerStyle);
        foreach ([2, 3, 4] as $r) {
            $sheet->getRowDimension($r)->setRowHeight(20);
        }

        $toRoman = function (int $num): string {
            $map = [1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
            $result = '';
            foreach ($map as $value => $symbol) {
                while ($num >= $value) {
                    $result .= $symbol;
                    $num -= $value;
                }
            }

            return $result;
        };

        $row = 5;
        foreach ($aktivitas as $akIndex => $ak) {
            $roman = $toRoman($akIndex + 1);
            $sheet->setCellValue("A{$row}", "{$roman}.");
            $sheet->setCellValue("B{$row}", $ak->jenis_kegiatan);
            $sheet->getStyle("A{$row}:S{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD6E4F0']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;

            foreach ($ak->uraianKegiatan as $ukIndex => $uraian) {
                $k = $uraian->kontrak->first();
                $vendor = $k?->vendor;
                $volParts = $uraian->volume ? explode(' ', $uraian->volume, 2) : ['', ''];
                $volAngka = $volParts[0] ?? '';
                $volSatuan = $volParts[1] ?? '';
                $persen = '';
                if ($uraian->anggaran_hps > 0 && $k?->nominal_kontrak) {
                    $persen = round(($k->nominal_kontrak / $uraian->anggaran_hps) * 100, 2).'%';
                }
                $keterangan = '';
                if ($k && $k->tanggal_mulai && $k->tanggal_akhir && $k->tanggal_mulai->year !== $k->tanggal_akhir->year) {
                    $keterangan = 'Multi Year';
                }
                $progressText = '';
                if ($k && $k->progress->isNotEmpty()) {
                    $lines = [];
                    foreach ($k->progress->whereNull('parent_id') as $p) {
                        $pMulai = $p->getRawOriginal('tanggal_mulai') ? date('d/m/Y', strtotime($p->getRawOriginal('tanggal_mulai'))) : '';
                        $pAkhir = $p->getRawOriginal('tanggal_akhir') ? date('d/m/Y', strtotime($p->getRawOriginal('tanggal_akhir'))) : '';
                        $uraianP = $p->uraian_progress ?? '';
                        $status = strtoupper($p->status ?? 'DRAFT');
                        $line = "• {$uraianP}";
                        if ($pMulai || $pAkhir) {
                            $line .= " ({$pMulai} s/d {$pAkhir})";
                        }
                        $line .= " [{$status}]";
                        $lines[] = $line;
                    }
                    $progressText = implode("\n", $lines);
                }
                $namaVendor = $vendor ? ($vendor->jenis_vendor === 'Pribadi' ? $vendor->nama_vendor : trim("{$vendor->jenis_vendor} {$vendor->nama_vendor}")) : '';

                $sheet->setCellValue("A{$row}", $ukIndex + 1);
                $sheet->setCellValue("B{$row}", $uraian->uraian_kegiatan);
                $sheet->setCellValue("C{$row}", $volAngka);
                $sheet->setCellValue("D{$row}", $volSatuan);
                $sheet->setCellValue("E{$row}", $uraian->anggaran_rab ?? '');
                $sheet->setCellValue("F{$row}", $uraian->anggaran_hps ?? '');
                $sheet->setCellValue("G{$row}", $uraian->kak_no ?? '');
                $sheet->setCellValue("H{$row}", $uraian->kak_spesifikasi ?? '');
                $sheet->setCellValue("I{$row}", $k?->no_kontrak ?? '');
                $sheet->setCellValue("J{$row}", $k?->tanggal_kontrak?->format('d/m/Y') ?? '');
                $sheet->setCellValue("K{$row}", $k?->nominal_kontrak ?? '');
                $sheet->setCellValue("L{$row}", $persen);
                $sheet->setCellValue("M{$row}", $k?->tanggal_mulai?->format('d/m/Y') ?? '');
                $sheet->setCellValue("N{$row}", $k?->tanggal_akhir?->format('d/m/Y') ?? '');
                $sheet->setCellValue("O{$row}", $namaVendor);
                $sheet->setCellValue("P{$row}", $vendor?->direktur ?? '');
                $sheet->setCellValue("Q{$row}", $k?->pelaksana ?? '');
                $sheet->setCellValue("R{$row}", $progressText);
                $sheet->setCellValue("S{$row}", $keterangan);

                if ($uraian->anggaran_rab) {
                    $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0');
                }
                if ($uraian->anggaran_hps) {
                    $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
                }
                if ($k?->nominal_kontrak) {
                    $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0');
                }

                $sheet->getStyle("A{$row}:S{$row}")->applyFromArray([
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($ukIndex % 2 === 0) {
                    $sheet->getStyle("A{$row}:S{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7FBFF');
                }
                $row++;
            }
        }

        $lastRow = $row - 1;
        if ($lastRow >= 1) {
            $sheet->getStyle("A1:S{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF9DBCD4']]],
            ]);
        }

        foreach (['A' => 6, 'B' => 35, 'C' => 8, 'D' => 12, 'E' => 16, 'F' => 16, 'G' => 12, 'H' => 20, 'I' => 20, 'J' => 14, 'K' => 16, 'L' => 12, 'M' => 14, 'N' => 14, 'O' => 22, 'P' => 18, 'Q' => 18, 'R' => 40, 'S' => 15] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A5');

        $filename = "Matriks Kegiatan-{$year}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function resolveVendor(array $data): ?int
    {
        $namaVendor = trim($data['nama_vendor'] ?? '');
        if ($namaVendor === '') {
            return null;
        }

        $jenisVendor = $data['jenis_vendor'] ?? 'PT';
        $direktur = $data['direktur'] ?? null;

        if ($jenisVendor === 'Pribadi' && empty($direktur)) {
            $direktur = $namaVendor;
        }

        $vendor = MonevVendor::firstOrCreate(
            ['nama_vendor' => $namaVendor, 'jenis_vendor' => $jenisVendor],
            ['direktur' => $direktur, 'no_hp' => $data['no_hp'] ?? null]
        );

        $vendor->update([
            'direktur' => $direktur ?? $vendor->direktur,
            'no_hp' => $data['no_hp'] ?? $vendor->no_hp,
        ]);

        return $vendor->id;
    }
}
