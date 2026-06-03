<?php

namespace App\Http\Controllers\PenilaianPerilaku;

use App\Exports\PenilaianPerilakuExport;
use App\Http\Controllers\Controller;
use App\Models\PenilaianPerilaku;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PenilaianPerilakuController extends Controller
{
    public function home(): Response
    {
        $user = Auth::user();

        return Inertia::render('PenilaianPerilaku/Home', [
            'user' => [
                'name' => $user->name,
                'role' => $user->role ?? 'staf',
                'penilaian_aktif' => (bool) ($user->penilaian_aktif ?? false),
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu';
        $isKepala = $user->role === 'kepala_unit';

        if (! $isAdmin && ! $isKepala) {
            abort(403);
        }

        $inAutoPeriod = self::isPenilaianPeriod();
        $penilaianBelumAktif = $isKepala && ! $user->penilaian_aktif && ! $inAutoPeriod;

        $periode = $request->get('periode', Carbon::now()->format('Y-m'));
        $statusFilter = $request->get('status', 'semua');

        if ($isAdmin) {
            $pegawaiQuery = User::with('unit')
                ->where('role', 'staf')
                ->orderBy('name', 'asc');
        } else {
            $pegawaiQuery = User::with('unit')
                ->where('kode_unit', $user->kode_unit)
                ->where('role', 'staf')
                ->where(function ($q) {
                    $q->whereNull('status_pegawai')
                        ->orWhereNotIn('status_pegawai', ['PNS', 'CPNS', 'PPPK', 'PPPK Paruh Waktu']);
                })
                ->orderBy('name', 'asc');
        }

        $pegawaiList = $pegawaiQuery->get();

        $penilaianMap = PenilaianPerilaku::where('periode', $periode)
            ->whereIn('user_id', $pegawaiList->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $data = $pegawaiList->map(function ($pegawai) use ($penilaianMap) {
            $penilaian = $penilaianMap->get($pegawai->id);

            return [
                'id' => $pegawai->id,
                'name' => $pegawai->name,
                'email' => $pegawai->email,
                'role' => $pegawai->role,
                'status_pegawai' => $pegawai->status_pegawai,
                'kode_unit' => $pegawai->kode_unit,
                'unit_nama' => $pegawai->unit ? $pegawai->unit->nama_unit : '-',
                'penilaian_id' => $penilaian ? $penilaian->id : null,
                'status_penilaian' => $penilaian ? $penilaian->status : 'belum_dinilai',
            ];
        });

        if ($penilaianBelumAktif) {
            $data = $data->filter(fn ($d) => $d['status_penilaian'] === 'selesai')->values();
        } elseif ($statusFilter === 'selesai') {
            $data = $data->filter(fn ($d) => $d['status_penilaian'] === 'selesai')->values();
        } elseif ($statusFilter === 'belum_dinilai') {
            $data = $data->filter(fn ($d) => $d['status_penilaian'] === 'belum_dinilai')->values();
        }

        return Inertia::render('PenilaianPerilaku/Pegawai', [
            'pegawaiList' => $data,
            'periode' => $periode,
            'statusFilter' => $statusFilter,
            'penilaianBelumAktif' => $penilaianBelumAktif,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu';
        $isKepala = $user->role === 'kepala_unit';

        if (! $isAdmin && ! $isKepala) {
            abort(403);
        }

        $validValues = 'nullable|in:di_atas_ekspektasi,sesuai_ekspektasi,di_bawah_ekspektasi';

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'periode' => 'required|string',
            'berorientasi_pelayanan' => $validValues,
            'akuntabel' => $validValues,
            'kompeten' => $validValues,
            'harmonis' => $validValues,
            'loyal' => $validValues,
            'adaptif' => $validValues,
            'kolaboratif' => $validValues,
        ]);

        $pegawai = User::findOrFail($request->user_id);

        if ($isKepala && $pegawai->kode_unit !== $user->kode_unit) {
            abort(403);
        }

        if ($isKepala && in_array($pegawai->status_pegawai, ['PNS', 'CPNS', 'PPPK', 'PPPK Paruh Waktu'])) {
            abort(403);
        }

        PenilaianPerilaku::updateOrCreate(
            ['user_id' => $request->user_id, 'periode' => $request->periode],
            [
                'user_id' => $request->user_id,
                'penilai_id' => $user->id,
                'kode_unit' => $pegawai->kode_unit,
                'periode' => $request->periode,
                'berorientasi_pelayanan' => $request->berorientasi_pelayanan,
                'akuntabel' => $request->akuntabel,
                'kompeten' => $request->kompeten,
                'harmonis' => $request->harmonis,
                'loyal' => $request->loyal,
                'adaptif' => $request->adaptif,
                'kolaboratif' => $request->kolaboratif,
                'status' => 'selesai',
            ]
        );

        return redirect()->back()->with('success', 'Penilaian berhasil disimpan!');
    }

    public function update(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu';
        $isKepala = $user->role === 'kepala_unit';

        if (! $isAdmin && ! $isKepala) {
            abort(403);
        }

        $penilaian = PenilaianPerilaku::findOrFail($id);

        if ($isKepala && $penilaian->kode_unit !== $user->kode_unit) {
            abort(403);
        }

        $validValues = 'nullable|in:di_atas_ekspektasi,sesuai_ekspektasi,di_bawah_ekspektasi';

        $request->validate([
            'berorientasi_pelayanan' => $validValues,
            'akuntabel' => $validValues,
            'kompeten' => $validValues,
            'harmonis' => $validValues,
            'loyal' => $validValues,
            'adaptif' => $validValues,
            'kolaboratif' => $validValues,
        ]);

        $penilaian->update([
            'penilai_id' => $user->id,
            'berorientasi_pelayanan' => $request->berorientasi_pelayanan,
            'akuntabel' => $request->akuntabel,
            'kompeten' => $request->kompeten,
            'harmonis' => $request->harmonis,
            'loyal' => $request->loyal,
            'adaptif' => $request->adaptif,
            'kolaboratif' => $request->kolaboratif,
            'status' => 'selesai',
        ]);

        return redirect()->back()->with('success', 'Penilaian berhasil diupdate!');
    }

    public function show(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'periode' => 'required|string',
        ]);

        $penilaian = PenilaianPerilaku::where('user_id', $request->user_id)
            ->where('periode', $request->periode)
            ->with('penilai')
            ->first();

        return response()->json([
            'penilaian' => $penilaian,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $periode = $request->get('periode', Carbon::now()->format('Y-m'));
        $filename = 'penilaian-perilaku-'.$periode.'.xlsx';

        return Excel::download(new PenilaianPerilakuExport($periode), $filename);
    }

    public function pengaturan(): Response
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $kepalaList = User::with('unit')
            ->where('role', 'kepala_unit')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($k) {
                return [
                    'id' => $k->id,
                    'name' => $k->name,
                    'unit_nama' => $k->unit ? $k->unit->nama_unit : '-',
                    'penilaian_aktif' => (bool) $k->penilaian_aktif,
                ];
            });

        return Inertia::render('PenilaianPerilaku/Pengaturan', [
            'kepalaList' => $kepalaList,
            'isPeriod' => self::isPenilaianPeriod(),
        ]);
    }

    public function togglePenilaian(int $id): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $kepala = User::where('role', 'kepala_unit')->findOrFail($id);
        $kepala->penilaian_aktif = ! $kepala->penilaian_aktif;
        $kepala->save();

        return response()->json([
            'success' => true,
            'penilaian_aktif' => $kepala->penilaian_aktif,
        ]);
    }

    public function toggleAllPenilaian(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $aktif = $request->boolean('aktif');
        User::where('role', 'kepala_unit')->update(['penilaian_aktif' => $aktif]);

        return response()->json([
            'success' => true,
            'aktif' => $aktif,
        ]);
    }

    public function indexSaya(Request $request): Response
    {
        $user = Auth::user();
        $tahun = $request->get('tahun', Carbon::now()->year);

        $namaBulan = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        $konversi = [
            'di_atas_ekspektasi' => 3,
            'sesuai_ekspektasi' => 2,
            'di_bawah_ekspektasi' => 1,
        ];

        $unsurKeys = ['berorientasi_pelayanan', 'akuntabel', 'kompeten', 'harmonis', 'loyal', 'adaptif', 'kolaboratif'];

        $penilaianList = PenilaianPerilaku::where('user_id', $user->id)
            ->where('periode', 'like', $tahun.'-%')
            ->where('status', 'selesai')
            ->orderBy('periode', 'asc')
            ->get();

        $data = $penilaianList->map(function ($p) use ($namaBulan, $konversi, $unsurKeys) {
            $bulan = substr($p->periode, 5, 2);

            $nilaiUnsur = [];
            $totalNilai = 0;
            foreach ($unsurKeys as $key) {
                $nilai = $konversi[$p->{$key}] ?? 0;
                $nilaiUnsur[$key] = $nilai;
                $totalNilai += $nilai;
            }

            $rataRata = round($totalNilai / 7, 2);

            if ($rataRata >= 2.51) {
                $keterangan = 'Di Atas Ekspektasi';
            } elseif ($rataRata >= 1.5) {
                $keterangan = 'Sesuai Ekspektasi';
            } else {
                $keterangan = 'Di Bawah Ekspektasi';
            }

            return array_merge([
                'id' => $p->id,
                'bulan' => $namaBulan[$bulan] ?? $bulan,
                'periode' => $p->periode,
            ], $nilaiUnsur, [
                'rata_rata' => $rataRata,
                'keterangan' => $keterangan,
            ]);
        });

        return Inertia::render('PenilaianPerilaku/Saya', [
            'penilaianList' => $data,
            'tahun' => (int) $tahun,
            'userName' => $user->name,
        ]);
    }

    public static function isPenilaianPeriod(): bool
    {
        $day = now()->day;

        return $day >= 15 || $day <= 5;
    }
}
