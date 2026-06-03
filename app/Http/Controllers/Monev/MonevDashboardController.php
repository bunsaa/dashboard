<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\Aktivitas;
use App\Models\Monev\AnggPengadaan;
use App\Models\Monev\Instansi;
use App\Models\Monev\Kontrak;
use App\Models\Monev\UnitKerja;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonevDashboardController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $user        = auth()->user();
        $isFullAdmin = $user->role === 'admin_mutu';
        $year        = (int) ($request->get('year') ?: date('Y'));
        $endDate     = "{$year}-12-31";

        $instansiId  = null;
        $unitKerjaId = null;

        if ($isFullAdmin) {
            // admin_mutu: default to RSUD Tarakan (satu-satunya instansi), bisa filter per unit
            $instansiId  = $request->get('instansi_id') ?: 1;
            $unitKerjaId = $request->get('unit_kerja_id') ?: null;
        } else {
            // kepala_unit & staf: resolve unit_kerja_id dari kode_unit user
            $unitKerja   = $user->kode_unit ? UnitKerja::where('kode_unit_kerja', $user->kode_unit)->first() : null;
            $unitKerjaId = $unitKerja?->id;
        }

        $aktivitasQuery = Aktivitas::with([
            'uraianKegiatan.kontrak.progress' => function ($q) {
                $q->whereNull('parent_id')->with('children')->orderBy('tanggal_mulai');
            },
        ])->oldest();

        if ($unitKerjaId) {
            $aktivitasQuery->where('unit_kerja_id', $unitKerjaId);
        } elseif ($instansiId) {
            $aktivitasQuery->where(function ($q) use ($instansiId) {
                $q->whereHas('unitKerja', fn ($qq) => $qq->where('instansi_id', $instansiId))
                    ->orWhereNull('unit_kerja_id');
            });
        }

        $aktivitasQuery->whereHas('uraianKegiatan.kontrak', function ($q) use ($year) {
            $q->where(function ($qq) use ($year) {
                $qq->whereYear('tanggal_kontrak', $year)
                    ->orWhereYear('tanggal_mulai', $year);
            });
        });

        $aktivitas = $aktivitasQuery->get();

        $chartData = $aktivitas->map(function ($ak, $index) {
            $uraians      = $ak->uraianKegiatan;
            $totalRab     = (float) $uraians->sum('anggaran_rab');
            $totalHps     = (float) $uraians->sum('anggaran_hps');
            $allKontrak   = $uraians->flatMap(fn ($u) => $u->kontrak);
            $totalNominal = (float) $allKontrak->sum('nominal_kontrak');
            $persen       = $totalHps > 0
                ? round($totalNominal / $totalHps * 100, 1)
                : ($totalRab > 0 ? round($totalNominal / $totalRab * 100, 1) : 0);

            return [
                'index'           => $index + 1,
                'label'           => $ak->jenis_kegiatan,
                'total_rab'       => $totalRab,
                'total_hps'       => $totalHps,
                'total_nominal'   => $totalNominal,
                'persen'          => $persen,
                'count_uraian'    => $uraians->count(),
                'count_kontrak'   => $allKontrak->count(),
                'coverage_persen' => $uraians->count() > 0
                    ? round($allKontrak->count() / $uraians->count() * 100, 1)
                    : 0,
            ];
        });

        $timeline = $aktivitas->map(function ($ak) use ($year, $endDate) {
            $items = $ak->uraianKegiatan->map(function ($uk) use ($year, $endDate) {
                $k = $uk->kontrak->first();
                if (! $k || ! $k->tanggal_kontrak || ! $k->tanggal_akhir) {
                    return null;
                }

                $akhirClipped    = min($k->tanggal_akhir->format('Y-m-d'), $endDate);
                $progressEntries = $k->progress->map(function ($p) {
                    $rawMulai   = $p->getRawOriginal('tanggal_mulai');
                    $rawAkhir   = $p->getRawOriginal('tanggal_akhir');
                    $notStarted = $rawMulai && strtotime($rawMulai) > time();
                    $children   = $p->children->map(function ($c) {
                        $cMulai      = $c->getRawOriginal('tanggal_mulai');
                        $cAkhir      = $c->getRawOriginal('tanggal_akhir');
                        $cNotStarted = $cMulai && strtotime($cMulai) > time();

                        return [
                            'label'       => $c->uraian_progress ?: ($c->keterangan ?: 'Sub-uraian'),
                            'tgl_mulai'   => $cMulai,
                            'tgl_akhir'   => $cAkhir,
                            'rencana'     => 100.0,
                            'realisasi'   => $cNotStarted ? 0.0 : $this->calcPersenFromDates($cMulai, $cAkhir),
                            'keterangan'  => $c->keterangan,
                            'not_started' => $cNotStarted,
                        ];
                    })->values()->toArray();

                    return [
                        'label'       => $p->uraian_progress ?: ($p->keterangan ?: $p->tipe),
                        'tgl_mulai'   => $rawMulai,
                        'tgl_akhir'   => $rawAkhir,
                        'rencana'     => 100.0,
                        'realisasi'   => $notStarted ? 0.0 : $this->calcPersenFromDates($rawMulai, $rawAkhir),
                        'keterangan'  => $p->keterangan,
                        'not_started' => $notStarted,
                        'children'    => $children,
                    ];
                })->values()->toArray();

                array_unshift($progressEntries, [
                    'label'       => 'Kontrak Kerja',
                    'tgl_mulai'   => $k->tanggal_kontrak?->format('Y-m-d'),
                    'tgl_akhir'   => $k->tanggal_akhir?->format('Y-m-d'),
                    'rencana'     => 100.0,
                    'realisasi'   => 100.0,
                    'keterangan'  => $k->no_kontrak,
                    'not_started' => false,
                    'children'    => [],
                ]);

                $allRealisasi = [];
                foreach ($progressEntries as $pe) {
                    $allRealisasi[] = $pe['realisasi'];
                    foreach ($pe['children'] as $ch) {
                        $allRealisasi[] = $ch['realisasi'];
                    }
                }
                $persenProgress = count($allRealisasi) > 0
                    ? round(array_sum($allRealisasi) / count($allRealisasi), 1)
                    : 0;

                return [
                    'label'           => $uk->uraian_kegiatan,
                    'tanggal_kontrak' => $k->tanggal_kontrak->format('Y-m-d'),
                    'tanggal_akhir'   => $akhirClipped,
                    'no_kontrak'      => $k->no_kontrak,
                    'progress'        => $progressEntries,
                    'persen_progress' => $persenProgress,
                ];
            })->filter()->values();

            if ($items->isEmpty()) {
                return null;
            }

            return ['label' => $ak->jenis_kegiatan, 'items' => $items];
        })->filter()->values();

        $kurvaS    = $this->buildKurvaS($instansiId, $unitKerjaId, $year, $endDate);
        $allUraian = $aktivitas->flatMap(fn ($ak) => $ak->uraianKegiatan);
        $allKontrak = $allUraian->flatMap(fn ($u) => $u->kontrak);

        $anggaranQuery = AnggPengadaan::where('tahun', $year);
        if ($unitKerjaId) {
            $anggaranQuery->where('unit_kerja_id', $unitKerjaId);
        } elseif ($instansiId) {
            $anggaranQuery->where('instansi_id', $instansiId);
        }
        $anggaranRecord = $anggaranQuery->first();
        $anggaranData   = $anggaranRecord ? [
            'id'           => $anggaranRecord->id,
            'nominal'      => $anggaranRecord->nominal,
            'edit_count'   => $anggaranRecord->edit_count,
            'edit_history' => $anggaranRecord->edit_history ?? [],
            'created_by'   => $anggaranRecord->created_by,
        ] : null;

        return Inertia::render('Monev/Dashboard', [
            'chartData'        => $chartData,
            'timeline'         => $timeline,
            'kurvaS'           => $kurvaS,
            'totalAktivitas'   => $aktivitas->count(),
            'totalKontrak'     => $allKontrak->count(),
            'totalNilai'       => (float) $allKontrak->sum('nominal_kontrak'),
            'totalRab'         => (float) $allUraian->sum('anggaran_rab'),
            'anggaranData'     => $anggaranData,
            'year'             => $year,
            'instansiList'     => $isFullAdmin ? Instansi::orderBy('nama_instansi')->get(['id', 'nama_instansi']) : collect(),
            'selectedInstansi' => $instansiId ? (int) $instansiId : null,
            'isAdmin'          => $isFullAdmin,
        ]);
    }

    private function calcPersenFromDates(?string $mulai, ?string $akhir): float
    {
        if (! $mulai || ! $akhir) {
            return 0.0;
        }
        $start   = strtotime($mulai);
        $end     = strtotime($akhir);
        $total   = $end - $start;
        if ($total <= 0) {
            return 100.0;
        }
        $elapsed = time() - $start;
        if ($elapsed <= 0) {
            return 0.0;
        }

        return (float) min(round($elapsed / $total * 100, 1), 100);
    }

    private function buildKurvaS(?string $instansiId, ?int $unitKerjaId, int $year, string $endDate): array
    {
        $bulanNames   = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agt', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        $kontrakQuery = Kontrak::with([
            'progress' => fn ($q) => $q->whereNull('parent_id')->orderBy('tanggal_mulai'),
        ])->whereNotNull('tanggal_mulai')->whereNotNull('tanggal_akhir')
            ->where(function ($q) use ($year) {
                $q->whereYear('tanggal_kontrak', $year)->orWhereYear('tanggal_mulai', $year);
            });

        if ($unitKerjaId) {
            $kontrakQuery->whereHas('uraianKegiatan.aktivitas', fn ($q) => $q->where('unit_kerja_id', $unitKerjaId));
        } elseif ($instansiId) {
            $kontrakQuery->whereHas('uraianKegiatan.aktivitas', function ($q) use ($instansiId) {
                $q->where(function ($qq) use ($instansiId) {
                    $qq->whereHas('unitKerja', fn ($qqq) => $qqq->where('instansi_id', $instansiId))
                        ->orWhereNull('unit_kerja_id');
                });
            });
        }

        $kontrakList = $kontrakQuery->get();
        if ($kontrakList->isEmpty()) {
            return ['labels' => [], 'lines' => []];
        }

        $labels    = [];
        $monthEnds = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[]    = ($bulanNames[$m] ?? '?')." '".substr((string) $year, 2);
            $monthEnds[] = mktime(23, 59, 59, $m + 1, 0, $year);
        }

        $totalNominal = (float) $kontrakList->sum('nominal_kontrak');
        $n            = $kontrakList->count();
        $useEqual     = $totalNominal <= 0;
        $today        = mktime(23, 59, 59, (int) date('n'), (int) date('j'), (int) date('Y'));
        $rencanaArr   = array_fill(0, 12, 0.0);
        $realisasiArr = array_fill(0, 12, 0.0);

        foreach ($kontrakList as $k) {
            $kMulai = $k->tanggal_mulai ? $k->tanggal_mulai->timestamp : null;
            $kAkhir = $k->tanggal_akhir ? $k->tanggal_akhir->timestamp : null;
            if (! $kMulai || ! $kAkhir || $kAkhir <= $kMulai) {
                continue;
            }
            $kDuration = $kAkhir - $kMulai;
            $bobot     = $useEqual ? (100.0 / $n) : ((float) ($k->nominal_kontrak ?? 0) / $totalNominal * 100.0);
            $entries   = $k->progress
                ->filter(fn ($p) => $p->getRawOriginal('tanggal_mulai') && $p->getRawOriginal('tanggal_akhir'))
                ->map(fn ($p) => [
                    'mulai' => strtotime($p->getRawOriginal('tanggal_mulai')),
                    'akhir' => strtotime($p->getRawOriginal('tanggal_akhir')),
                ])->values()->toArray();
            $nEntries  = count($entries);

            foreach ($monthEnds as $mi => $mEnd) {
                $rencanaRatio      = max(0.0, min(1.0, ($mEnd - $kMulai) / $kDuration));
                $rencanaArr[$mi]  += $bobot * $rencanaRatio;

                if ($nEntries > 0) {
                    $sumCompletion = 0.0;
                    foreach ($entries as $e) {
                        $eDuration = $e['akhir'] - $e['mulai'];
                        if ($eDuration <= 0) {
                            $sumCompletion += min($today, $mEnd) >= $e['mulai'] ? 1.0 : 0.0;
                        } else {
                            $sumCompletion += max(0.0, min(1.0, (min($today, $mEnd) - $e['mulai']) / $eDuration));
                        }
                    }
                    $realisasiArr[$mi] += $bobot * ($sumCompletion / $nEntries);
                } else {
                    $realisasiRatio     = max(0.0, min(1.0, (min($today, $mEnd) - $kMulai) / $kDuration));
                    $realisasiArr[$mi] += $bobot * $realisasiRatio;
                }
            }
        }

        return [
            'labels' => $labels,
            'lines'  => [[
                'label'     => 'Kurva S',
                'rencana'   => array_map(fn ($v) => round($v, 1), $rencanaArr),
                'realisasi' => array_map(fn ($v) => round($v, 1), $realisasiArr),
            ]],
        ];
    }
}
