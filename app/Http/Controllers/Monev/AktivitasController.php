<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\Aktivitas;
use App\Models\Monev\AnggPengadaan;
use App\Models\Monev\Instansi;
use App\Models\Monev\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AktivitasController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $user         = auth()->user();
        $isFullAdmin  = $user->role === 'admin_mutu';
        $isKepalaUnit = $user->role === 'kepala_unit';
        $canCrud      = $isFullAdmin || $isKepalaUnit;

        if ($isFullAdmin) {
            // admin_mutu: default ke RSUD Tarakan (satu-satunya instansi), bisa filter per unit
            $instansiId  = $request->get('instansi_id') ?: 1;
            $unitKerjaId = $request->get('unit_kerja_id');
        } else {
            // kepala_unit & staf: resolve unit_kerja_id dari kode_unit user
            $instansiId  = null;
            $unitKerja   = $user->kode_unit ? UnitKerja::where('kode_unit_kerja', $user->kode_unit)->first() : null;
            $unitKerjaId = $unitKerja?->id;
        }

        $query = Aktivitas::with(['uraianKegiatan', 'unitKerja.instansi'])->oldest();

        if ($unitKerjaId) {
            $query->where('unit_kerja_id', $unitKerjaId);
        } elseif ($instansiId) {
            $query->where(function ($q) use ($instansiId) {
                $q->whereHas('unitKerja', fn ($qq) => $qq->where('instansi_id', $instansiId))
                    ->orWhereNull('unit_kerja_id');
            });
        } else {
            $query->whereRaw('0=1');
        }

        $unitKerjaList = ($isFullAdmin && $instansiId)
            ? UnitKerja::where('instansi_id', $instansiId)->orderBy('nama_unit_kerja')->get(['id', 'nama_unit_kerja'])
            : collect();

        $aktivitasList = $query->get();
        $totalRab      = $aktivitasList->flatMap(fn ($a) => $a->uraianKegiatan)->sum('anggaran_rab');

        $year          = (int) date('Y');
        $anggaranQuery = AnggPengadaan::where('tahun', $year);
        if ($unitKerjaId) {
            $anggaranQuery->where('unit_kerja_id', $unitKerjaId);
        } elseif ($instansiId) {
            $anggaranQuery->where('instansi_id', $instansiId)->whereNull('unit_kerja_id');
        }
        $anggaran = $anggaranQuery->first();

        return Inertia::render('Monev/Aktivitas', [
            'aktivitas'         => $aktivitasList,
            'instansiList'      => $isFullAdmin ? Instansi::orderBy('nama_instansi')->get(['id', 'nama_instansi']) : collect(),
            'unitKerjaList'     => $unitKerjaList,
            'selectedInstansi'  => $instansiId ? (int) $instansiId : null,
            'selectedUnit'      => $unitKerjaId ? (int) $unitKerjaId : null,
            'anggaranPengadaan' => $anggaran ? (float) $anggaran->nominal : null,
            'totalRab'          => (float) $totalRab,
            'tahun'             => $year,
            'isAdmin'           => $isFullAdmin,
            'canCrud'           => $canCrud,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin_mutu', 'kepala_unit'])) {
            abort(403);
        }

        $validated = $request->validate([
            'jenis_kegiatan' => 'required|string|max:255',
            'unit_kerja_id'  => ['nullable', Rule::exists(UnitKerja::class, 'id')],
        ]);

        Aktivitas::create($validated);

        return redirect()->back()->with('success', 'Jenis kegiatan berhasil ditambahkan.');
    }

    public function update(Request $request, Aktivitas $aktivitas)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin_mutu', 'kepala_unit'])) {
            abort(403);
        }

        $validated = $request->validate([
            'jenis_kegiatan' => 'required|string|max:255',
            'unit_kerja_id'  => ['nullable', Rule::exists(UnitKerja::class, 'id')],
        ]);

        $aktivitas->update($validated);

        return redirect()->back()->with('success', 'Jenis kegiatan berhasil diperbarui.');
    }

    public function destroy(Aktivitas $aktivitas)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin_mutu', 'kepala_unit'])) {
            abort(403);
        }

        $aktivitas->delete();

        return redirect()->back()->with('success', 'Aktivitas berhasil dihapus.');
    }
}
