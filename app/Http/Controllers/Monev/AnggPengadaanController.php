<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\AnggPengadaan;
use Illuminate\Http\Request;

class AnggPengadaanController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'nominal'       => 'required|numeric|min:0',
            'tahun'         => 'required|integer|min:2000|max:2100',
            'unit_kerja_id' => 'nullable|exists:unit_kerja,id',
            'instansi_id'   => 'nullable|exists:instansi,id',
        ]);

        $isAdmin = in_array($user->role, ['admin_mutu', 'kepala_unit']);

        [$unitKerjaId, $instansiId] = $isAdmin
            ? [$data['unit_kerja_id'] ?? null, $data['instansi_id'] ?? null]
            : [$user->unit_kerja_id, null];

        $existing = AnggPengadaan::where('tahun', $data['tahun'])
            ->where('unit_kerja_id', $unitKerjaId)
            ->where('instansi_id', $instansiId)
            ->first();

        if ($existing) {
            if ($existing->edit_count >= 2) {
                return back()->withErrors(['nominal' => 'Anggaran pengadaan sudah mencapai batas edit (maksimal 2 kali).']);
            }

            $history   = $existing->edit_history ?? [];
            $history[] = [
                'tanggal'            => now()->format('Y-m-d H:i'),
                'nominal_sebelumnya' => $existing->nominal,
                'diubah_oleh'        => $user->name,
            ];

            $existing->update([
                'nominal'      => $data['nominal'],
                'edit_count'   => $existing->edit_count + 1,
                'edit_history' => $history,
            ]);
        } else {
            AnggPengadaan::create([
                'unit_kerja_id' => $unitKerjaId,
                'instansi_id'   => $instansiId,
                'tahun'         => $data['tahun'],
                'nominal'       => $data['nominal'],
                'edit_count'    => 0,
                'created_by'    => $user->name,
            ]);
        }

        return redirect()->back()->with('success', 'Anggaran pengadaan berhasil disimpan.');
    }
}
