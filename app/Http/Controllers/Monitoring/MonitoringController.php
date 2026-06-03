<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MonitoringController extends Controller
{
    /**
     * Rekap harian beda kelas peserta untuk H+1 dan H+2.
     * Kelas RM diambil dari Patient.SRBPJSClass (BPJSClass-001/002/003).
     *
     * @return object[]
     */
    private function queryRekap(): array
    {
        return DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT
                CONVERT(VARCHAR(10), CAST(apt.AppointmentDate AS DATE), 23) AS tanggal,
                COUNT(DISTINCT apt.AppointmentNo)                            AS jumlah
            FROM Appointment apt WITH (NOLOCK)
            INNER JOIN Guarantor g   WITH (NOLOCK) ON g.GuarantorID      = apt.GuarantorID
                                                   AND g.SRGuarantorType  = '09'
            INNER JOIN Patient   pat WITH (NOLOCK) ON pat.PatientID       = apt.PatientID
            OUTER APPLY (
                SELECT TOP 1 bp2.JnsKelas_kode, bp2.LastUpdateDateTime
                FROM BpjsPeserta bp2 WITH (NOLOCK)
                WHERE bp2.NoKartu = apt.GuarantorCardNo
                   OR bp2.NoKTP   = apt.Ssn
                ORDER BY CASE WHEN bp2.NoKartu = apt.GuarantorCardNo THEN 0 ELSE 1 END,
                         bp2.LastUpdateDateTime DESC
            ) bp
            WHERE CAST(apt.AppointmentDate AS DATE) IN (
                    CAST(GETDATE() AS DATE),
                    DATEADD(DAY, 1, CAST(GETDATE() AS DATE)),
                    DATEADD(DAY, 2, CAST(GETDATE() AS DATE))
                )
              AND apt.SRAppointmentStatus != '03'
              AND pat.SRBPJSClass != 'BPJSClass-00' + bp.JnsKelas_kode
              AND bp.LastUpdateDateTime <= DATEADD(MONTH, -2, GETDATE())
            GROUP BY CAST(apt.AppointmentDate AS DATE)
            ORDER BY CAST(apt.AppointmentDate AS DATE)
        SQL);
    }

    /**
     * Detail beda kelas peserta untuk tanggal tertentu.
     * Kelas RM diambil dari Patient.SRBPJSClass.
     *
     * @return object[]
     */
    private function queryDetail(string $tanggal): array
    {
        return DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT
                ROW_NUMBER() OVER (ORDER BY apt.AppointmentDate, pat.MedicalNo) AS No,
                pat.MedicalNo,
                bp.NoKartu                                                      AS NoKartu,
                pat.FullName                                                    AS NamaPasien,
                apt.AppointmentNo,
                r_today.RegistrationNo,
                CONVERT(VARCHAR(10), CAST(apt.AppointmentDate AS DATE), 103)   AS TanggalKunjungan,
                su.ServiceUnitName                                              AS NamaPoli,
                CASE pat.SRBPJSClass
                    WHEN 'BPJSClass-001' THEN 'Kelas I'
                    WHEN 'BPJSClass-002' THEN 'Kelas II'
                    WHEN 'BPJSClass-003' THEN 'Kelas III'
                    ELSE pat.SRBPJSClass
                END                                                             AS KelasRekamMedis,
                bp.JnsKelas_nama                                                AS KelasBpjs
            FROM Appointment apt WITH (NOLOCK)
            INNER JOIN Guarantor   g       WITH (NOLOCK) ON g.GuarantorID     = apt.GuarantorID
                                                        AND g.SRGuarantorType = '09'
            INNER JOIN Patient     pat     WITH (NOLOCK) ON pat.PatientID     = apt.PatientID
            INNER JOIN ServiceUnit su      WITH (NOLOCK) ON su.ServiceUnitID  = apt.ServiceUnitID
            OUTER APPLY (
                SELECT TOP 1 bp2.JnsKelas_kode, bp2.JnsKelas_nama, bp2.NoKartu, bp2.LastUpdateDateTime
                FROM BpjsPeserta bp2 WITH (NOLOCK)
                WHERE bp2.NoKartu = apt.GuarantorCardNo
                   OR bp2.NoKTP   = apt.Ssn
                ORDER BY CASE WHEN bp2.NoKartu = apt.GuarantorCardNo THEN 0 ELSE 1 END,
                         bp2.LastUpdateDateTime DESC
            ) bp
            LEFT  JOIN Registration r_today WITH (NOLOCK)
                ON r_today.AppointmentNo = apt.AppointmentNo
               AND r_today.IsVoid = 0
            WHERE CAST(apt.AppointmentDate AS DATE) = ?
              AND apt.SRAppointmentStatus != '03'
              AND pat.SRBPJSClass != 'BPJSClass-00' + bp.JnsKelas_kode
              AND bp.LastUpdateDateTime <= DATEADD(MONTH, -2, GETDATE())
            ORDER BY pat.MedicalNo
        SQL, [$tanggal]);
    }

    /**
     * Summary cards untuk hari ini:
     * - sudah_registrasi : pasien beda kelas yang sudah terdaftar hari ini
     * - rutin_kunjungan  : pasien beda kelas hari ini yang ≥2 kunjungan dalam 7 hari terakhir
     *
     * @return array{sudah_registrasi: int, rutin_kunjungan: array<array{MedicalNo: string, NamaPasien: string}>}
     */
    private function queryCardsHariIni(): array
    {
        $regRows = DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT COUNT(DISTINCT apt.AppointmentNo) AS jumlah
            FROM Appointment apt WITH (NOLOCK)
            INNER JOIN Guarantor   g   WITH (NOLOCK) ON g.GuarantorID    = apt.GuarantorID
                                                     AND g.SRGuarantorType = '09'
            INNER JOIN Patient     pat WITH (NOLOCK) ON pat.PatientID    = apt.PatientID
            OUTER APPLY (
                SELECT TOP 1 bp2.JnsKelas_kode, bp2.LastUpdateDateTime
                FROM BpjsPeserta bp2 WITH (NOLOCK)
                WHERE bp2.NoKartu = apt.GuarantorCardNo
                   OR bp2.NoKTP   = apt.Ssn
                ORDER BY CASE WHEN bp2.NoKartu = apt.GuarantorCardNo THEN 0 ELSE 1 END,
                         bp2.LastUpdateDateTime DESC
            ) bp
            INNER JOIN Registration r  WITH (NOLOCK) ON r.AppointmentNo  = apt.AppointmentNo
                                                     AND r.IsVoid         = 0
            WHERE CAST(apt.AppointmentDate AS DATE) = CAST(GETDATE() AS DATE)
              AND apt.SRAppointmentStatus != '03'
              AND pat.SRBPJSClass != 'BPJSClass-00' + bp.JnsKelas_kode
              AND bp.LastUpdateDateTime <= DATEADD(MONTH, -2, GETDATE())
        SQL);

        // CTE: get today's beda-kelas patients first (small set), then count only
        // their historical appointments — avoids full-table scan of Appointment.
        // Window: 7 days back, threshold: ≥2 visits.
        $rutinRows = DB::connection('sqlsrv_report')->select(<<<'SQL'
            WITH TodayBK AS (
                SELECT DISTINCT pat.PatientID, pat.MedicalNo, pat.FullName
                FROM Appointment apt WITH (NOLOCK)
                INNER JOIN Guarantor g   WITH (NOLOCK) ON g.GuarantorID     = apt.GuarantorID
                                                       AND g.SRGuarantorType = '09'
                INNER JOIN Patient   pat WITH (NOLOCK) ON pat.PatientID      = apt.PatientID
                OUTER APPLY (
                    SELECT TOP 1 bp2.JnsKelas_kode, bp2.LastUpdateDateTime
                    FROM BpjsPeserta bp2 WITH (NOLOCK)
                    WHERE bp2.NoKartu = apt.GuarantorCardNo
                       OR bp2.NoKTP   = apt.Ssn
                    ORDER BY CASE WHEN bp2.NoKartu = apt.GuarantorCardNo THEN 0 ELSE 1 END,
                             bp2.LastUpdateDateTime DESC
                ) bp
                WHERE CAST(apt.AppointmentDate AS DATE) = CAST(GETDATE() AS DATE)
                  AND apt.SRAppointmentStatus != '03'
                  AND pat.SRBPJSClass != 'BPJSClass-00' + bp.JnsKelas_kode
                  AND bp.LastUpdateDateTime <= DATEADD(MONTH, -2, GETDATE())
            )
            SELECT t.MedicalNo, t.FullName AS NamaPasien
            FROM TodayBK t
            WHERE (
                SELECT COUNT(*)
                FROM Appointment a2 WITH (NOLOCK)
                WHERE a2.PatientID = t.PatientID
                  AND (a2.IsCancel IS NULL OR a2.IsCancel = 0)
                  AND a2.AppointmentDate >= DATEADD(DAY, -7, CAST(GETDATE() AS DATE))
                  AND a2.AppointmentDate <  CAST(GETDATE() AS DATE)
            ) >= 2
            ORDER BY t.FullName
        SQL);

        return [
            'sudah_registrasi' => (int) ($regRows[0]->jumlah ?? 0),
            'rutin_kunjungan' => array_map(fn ($r) => [
                'MedicalNo' => $r->MedicalNo,
                'NamaPasien' => $r->NamaPasien,
            ], $rutinRows),
        ];
    }

    public function bedaKelas(): Response
    {
        $today = now()->format('Y-m-d');

        return Inertia::render('Monitoring/BedaKelasPeserta', [
            'rekap' => Inertia::defer(fn () => Cache::remember("monitoring.rekap.v2.{$today}", 3600, fn () => $this->queryRekap()), rescue: true),
            'cards' => Inertia::defer(fn () => Cache::remember("monitoring.cards.v2.{$today}", 3600, fn () => $this->queryCardsHariIni()), rescue: true),
        ]);
    }

    public function klaimBpjs(string $_current_team, Request $request): Response
    {
        $bulan = (string) $request->query('bulan', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $bulan = now()->format('Y-m');
        }

        [$year, $month] = explode('-', $bulan);
        $from = "{$year}-{$month}-01";
        $to = date('Y-m-d', mktime(0, 0, 0, (int) $month + 1, 1, (int) $year));

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $q = trim((string) $request->query('q', ''));
        $q = $q !== '' ? $q : null;

        // Safe WHERE clause fragment — no user input interpolated
        $searchClause = $q
            ? "AND (f.SepNo LIKE ? OR ISNULL(sep.NoMR, '') LIKE ? OR ISNULL(sep.NamaPasien, '') LIKE ? OR CONVERT(VARCHAR(10), f.TanggalVerifikasi, 23) LIKE ? OR CONVERT(VARCHAR(10), f.TanggalVerifikasi, 103) LIKE ?)"
            : '';
        $searchParams = $q ? ["%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%"] : [];

        $summaryKey = 'klaim_bpjs_summary_'.$bulan;
        $selisihKey = 'klaim_bpjs_selisih_'.$bulan;
        $rowsKey = 'klaim_bpjs_rows_'.$bulan.'_p'.$page.($q ? '_'.md5($q) : '');
        $rowsTtl = $q ? 60 : 300;

        try {
            // Cards — cached as plain array (never stdClass) to avoid unserialize issues
            $cards = Cache::remember($summaryKey, 1800, function () use ($from, $to) {
                $row = DB::connection('sqlsrv_report')->selectOne(<<<'SQL'
                    SELECT
                        COUNT(*) AS total_klaim,
                        SUM(BiayaDiajukan)                                               AS total_diajukan,
                        SUM(BiayaDisetujui)                                              AS total_disetujui,
                        SUM(BiayaDiajukan) - SUM(BiayaDisetujui)                        AS selisih,
                        SUM(CASE WHEN BiayaDiajukan != BiayaDisetujui THEN 1 ELSE 0 END) AS selisih_count
                    FROM bpjsfpk WITH (NOLOCK)
                    WHERE TanggalVerifikasi >= ? AND TanggalVerifikasi < ?
                SQL, [$from, $to]);

                return [
                    'total_klaim'     => (int) ($row?->total_klaim ?? 0),
                    'total_diajukan'  => (float) ($row?->total_diajukan ?? 0),
                    'total_disetujui' => (float) ($row?->total_disetujui ?? 0),
                    'selisih'         => (float) ($row?->selisih ?? 0),
                    'selisih_count'   => (int) ($row?->selisih_count ?? 0),
                ];
            });

            // Top 50 SEPs — cached as plain array
            $selisihDetail = Cache::remember($selisihKey, 1800, function () use ($from, $to) {
                return array_map(fn ($r) => [
                    'SepNo'      => $r->SepNo,
                    'NoRM'       => $r->NoRM,
                    'NamaPasien' => $r->NamaPasien,
                    'Selisih'    => (float) $r->Selisih,
                ], DB::connection('sqlsrv_report')->select(<<<'SQL'
                    SELECT TOP 50
                        f.SepNo,
                        ISNULL(sep.NoMR, '')       AS NoRM,
                        ISNULL(sep.NamaPasien, '') AS NamaPasien,
                        CAST(f.BiayaDiajukan - f.BiayaDisetujui AS DECIMAL(18,2)) AS Selisih
                    FROM bpjsfpk f WITH (NOLOCK)
                    LEFT JOIN BpjsSEP sep WITH (NOLOCK) ON sep.NoSEP = f.SepNo
                    WHERE f.TanggalVerifikasi >= ? AND f.TanggalVerifikasi < ?
                        AND f.BiayaDiajukan != f.BiayaDisetujui
                    ORDER BY CAST(f.BiayaDiajukan - f.BiayaDisetujui AS DECIMAL(18,2)) DESC
                SQL, [$from, $to]));
            });

            // Pagination total — when searching we need a separate count with JOIN
            if ($q !== null) {
                $countKey = 'klaim_bpjs_count_'.$bulan.'_'.md5($q);
                $total = Cache::remember($countKey, 60, function () use ($from, $to, $searchClause, $searchParams) {
                    $row = DB::connection('sqlsrv_report')->selectOne("
                        SELECT COUNT(*) AS total
                        FROM bpjsfpk f WITH (NOLOCK)
                        LEFT JOIN BpjsSEP sep WITH (NOLOCK) ON sep.NoSEP = f.SepNo
                        WHERE f.TanggalVerifikasi >= ? AND f.TanggalVerifikasi < ?
                        {$searchClause}
                    ", array_merge([$from, $to], $searchParams));

                    return (int) ($row?->total ?? 0);
                });
            } else {
                $total = $cards['total_klaim'];
            }

            // Paginated items — cached as plain array
            $items = Cache::remember($rowsKey, $rowsTtl, function () use ($from, $to, $searchClause, $searchParams, $offset, $perPage) {
                return array_map(fn ($r) => [
                    'No'                => (int) $r->No,
                    'SepNo'             => $r->SepNo,
                    'NoRM'              => $r->NoRM,
                    'NamaPasien'        => $r->NamaPasien,
                    'TanggalVerifikasi' => $r->TanggalVerifikasi,
                    'BiayaDiajukan'     => (float) $r->BiayaDiajukan,
                    'BiayaDisetujui'    => (float) $r->BiayaDisetujui,
                    'Selisih'           => (float) $r->Selisih,
                    'NoBAHV'            => $r->NoBAHV,
                ], DB::connection('sqlsrv_report')->select("
                    WITH Ranked AS (
                        SELECT
                            ROW_NUMBER() OVER (ORDER BY f.TanggalVerifikasi DESC, f.SepNo) AS No,
                            f.SepNo,
                            ISNULL(sep.NoMR, '')       AS NoRM,
                            ISNULL(sep.NamaPasien, '') AS NamaPasien,
                            CONVERT(VARCHAR(10), f.TanggalVerifikasi, 23) AS TanggalVerifikasi,
                            f.BiayaDiajukan,
                            f.BiayaDisetujui,
                            f.BiayaDiajukan - f.BiayaDisetujui AS Selisih,
                            f.NoBAHV
                        FROM bpjsfpk f WITH (NOLOCK)
                        LEFT JOIN BpjsSEP sep WITH (NOLOCK) ON sep.NoSEP = f.SepNo
                        WHERE f.TanggalVerifikasi >= ? AND f.TanggalVerifikasi < ?
                        {$searchClause}
                    )
                    SELECT * FROM Ranked
                    ORDER BY No
                    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
                ", array_merge([$from, $to], $searchParams, [$offset, $perPage])));
            });

            $pagination = [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
            ];
            $error = null;
        } catch (Throwable $e) {
            Log::error('klaimBpjs error', ['bulan' => $bulan, 'q' => $q, 'error' => $e->getMessage()]);
            $cards = ['total_klaim' => 0, 'total_diajukan' => 0, 'total_disetujui' => 0, 'selisih' => 0, 'selisih_count' => 0];
            $items = [];
            $selisihDetail = [];
            $pagination = ['current_page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1];
            $error = 'Tidak dapat terhubung ke database: '.$e->getMessage();
        }

        return Inertia::render('Monitoring/KlaimBpjs', [
            'bulan'          => $bulan,
            'q'              => $q ?? '',
            'cards'          => $cards,
            'items'          => $items,
            'selisih_detail' => $selisihDetail,
            'pagination'     => $pagination,
            'error'          => $error,
        ]);
    }

    public function bedaKelasDetail(string $_current_team, string $tanggal): JsonResponse
    {
        try {
            $detail = $this->queryDetail($tanggal);
        } catch (Throwable $e) {
            Log::error('bedaKelasDetail error', ['tanggal' => $tanggal, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Tidak dapat mengambil data: '.$e->getMessage()], 503);
        }

        return response()->json($detail);
    }
}
