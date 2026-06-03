<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\Aktivitas;
use App\Models\Monev\UraianKegiatan;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UraianKegiatanController extends Controller
{
    public function store(Request $request, Aktivitas $aktivitas)
    {
        $validated = $request->validate([
            'uraian_kegiatan' => 'required|string',
            'volume'          => 'nullable|string|max:100',
            'anggaran_rab'    => 'nullable|numeric|min:0',
            'anggaran_hps'    => 'nullable|numeric|min:0',
            'kak_no'          => 'nullable|string|max:100',
            'kak_spesifikasi' => 'nullable|string',
        ]);

        $validated['anggaran_rab'] = $validated['anggaran_rab'] ?? 0;
        $validated['anggaran_hps'] = $validated['anggaran_hps'] ?? 0;

        $aktivitas->uraianKegiatan()->create($validated);

        return redirect()->back()->with('success', 'Uraian kegiatan berhasil ditambahkan.');
    }

    public function update(Request $request, UraianKegiatan $uraianKegiatan)
    {
        $validated = $request->validate([
            'uraian_kegiatan' => 'required|string',
            'volume'          => 'nullable|string|max:100',
            'anggaran_rab'    => 'nullable|numeric|min:0',
            'anggaran_hps'    => 'nullable|numeric|min:0',
            'kak_no'          => 'nullable|string|max:100',
            'kak_spesifikasi' => 'nullable|string',
        ]);

        $validated['anggaran_rab'] = $validated['anggaran_rab'] ?? 0;
        $validated['anggaran_hps'] = $validated['anggaran_hps'] ?? 0;

        $uraianKegiatan->update($validated);

        return redirect()->back()->with('success', 'Uraian kegiatan berhasil diperbarui.');
    }

    public function import(Request $request, Aktivitas $aktivitas)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();

        $processed = 0;
        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell->getCalculatedValue();
            }

            $uraianKegiatan = trim((string) ($cells[0] ?? ''));
            if ($uraianKegiatan === '') {
                continue;
            }

            $str = fn ($v) => isset($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
            $num = fn ($v) => is_numeric($v ?? null) ? (float) $v : 0;

            $volJumlah = $str($cells[1] ?? null);
            $volSatuan = $str($cells[2] ?? null);
            $volume    = $volJumlah !== null ? trim("{$volJumlah} ".($volSatuan ?? '')) : null;

            $aktivitas->uraianKegiatan()->updateOrCreate(
                ['uraian_kegiatan' => $uraianKegiatan],
                [
                    'volume'          => $volume,
                    'anggaran_rab'    => $num($cells[3] ?? null),
                    'anggaran_hps'    => $num($cells[4] ?? null),
                    'kak_no'          => $str($cells[5] ?? null),
                    'kak_spesifikasi' => $str($cells[6] ?? null),
                ]
            );
            $processed++;
        }

        return redirect()->back()->with('success', "Berhasil memproses {$processed} uraian kegiatan.");
    }

    public function destroy(UraianKegiatan $uraianKegiatan)
    {
        $uraianKegiatan->delete();

        return redirect()->back()->with('success', 'Uraian kegiatan berhasil dihapus.');
    }
}
