<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DownloadReportController extends Controller
{
    /**
     * @return string[]
     */
    private function queryPoliList(string $registrationType = 'OPR'): array
    {
        $tahun = now()->year;

        return Cache::remember('poli_list_'.$registrationType.'_'.$tahun, 3600, function () use ($registrationType, $tahun) {
            $startDate = ($tahun - 1).'-01-01';
            $endDate = ($tahun + 1).'-01-01';

            $rows = DB::connection('sqlsrv_report')->select(<<<'SQL'
                SELECT DISTINCT su.ServiceUnitName AS NamaPoli
                FROM [dbo].[Registration] r WITH (NOLOCK)
                JOIN [dbo].[ServiceUnit]  su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
                WHERE r.SRRegistrationType = ?
                  AND r.RegistrationDate >= ?
                  AND r.RegistrationDate <  ?
                ORDER BY su.ServiceUnitName
            SQL, [$registrationType, $startDate, $endDate]);

            return array_map(fn ($r) => $r->NamaPoli, $rows);
        });
    }

    /**
     * Rekap per poli + dokter — dipakai untuk tampilan halaman web.
     * SRRegistrationType hanya difilter saat tidak ada poli spesifik dipilih,
     * agar angka cocok dengan hasil query Navicat saat poli dipilih.
     * Kolom tahun bersifat dinamis berdasarkan $fromYear s/d tahun berjalan.
     *
     * @return object[]
     */
    private function queryRekap(?string $poli = null, string $registrationType = 'OPR', int $fromYear = 0): array
    {
        $tahun = now()->year;
        if ($fromYear <= 0) {
            $fromYear = $tahun - 1;
        }
        $fromYear = max(2000, min($fromYear, $tahun));

        $startDate = $fromYear.'-01-01';
        $endDate = ($tahun + 1).'-01-01';
        $typeWhere = $poli === null ? 'AND r.SRRegistrationType = ?' : '';
        $poliWhere = $poli ? 'AND su.ServiceUnitName = ?' : '';

        // Dynamic CASE-WHEN columns for each year in range
        $yearCaseParts = [];
        $params = [];
        for ($y = $fromYear; $y <= $tahun; $y++) {
            $yearCaseParts[] = "SUM(CASE WHEN YEAR(r.RegistrationDate) = ? THEN 1 ELSE 0 END) AS [year_{$y}]";
            $params[] = $y;
        }
        $yearColumns = implode(",\n                ", $yearCaseParts);

        if ($poli === null) {
            $params[] = $registrationType;
        }
        $params[] = $startDate;
        $params[] = $endDate;
        if ($poli) {
            $params[] = $poli;
        }

        return DB::connection('sqlsrv_report')->select(<<<SQL
            SELECT
                ROW_NUMBER() OVER (ORDER BY su.ServiceUnitName, pm.ParamedicName) AS No,
                su.ServiceUnitName  AS NamaPoli,
                pm.ParamedicName    AS NamaDokter,
                {$yearColumns}
            FROM [dbo].[Registration] r  WITH (NOLOCK)
            JOIN [dbo].[ServiceUnit]  su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
            JOIN [dbo].[Paramedic]    pm WITH (NOLOCK) ON pm.ParamedicID   = r.ParamedicID
            WHERE 1=1
              {$typeWhere}
              AND r.RegistrationDate >= ?
              AND r.RegistrationDate <  ?
              {$poliWhere}
            GROUP BY su.ServiceUnitName, pm.ParamedicName
            ORDER BY su.ServiceUnitName, pm.ParamedicName
        SQL, $params);
    }

    /**
     * Rekap bulanan — dipakai untuk Sheet 1 file Excel.
     * SRRegistrationType hanya difilter saat tidak ada poli spesifik dipilih.
     *
     * @return object[]
     */
    private function queryRekapBulanan(?string $poli = null, string $registrationType = 'OPR', ?string $keyword = null, int $fromYear = 0): array
    {
        $tahun = now()->year;
        if ($fromYear <= 0) {
            $fromYear = $tahun - 1;
        }
        $fromYear = max(2000, min($fromYear, $tahun));
        $startDate = $fromYear.'-01-01';
        $endDate = ($tahun + 1).'-01-01';
        $typeWhere = ($poli === null && $keyword === null) ? 'AND r.SRRegistrationType = ?' : '';
        $poliWhere = $poli ? 'AND su.ServiceUnitName = ?' : '';
        $keywordWhere = $keyword ? 'AND CHARINDEX(?, su.ServiceUnitName) > 0' : '';

        $params = [];
        if ($poli === null && $keyword === null) {
            $params[] = $registrationType;
        }
        $params[] = $startDate;
        $params[] = $endDate;
        if ($poli) {
            $params[] = $poli;
        }
        if ($keyword) {
            $params[] = $keyword;
        }

        return DB::connection('sqlsrv_report')->select(<<<SQL
            SELECT
                YEAR(r.RegistrationDate)            AS TahunKunjungan,
                MONTH(r.RegistrationDate)           AS BulanKunjungan,
                DATENAME(MONTH, r.RegistrationDate) AS NamaBulan,
                COUNT(r.RegistrationNo)             AS JumlahPasien
            FROM [dbo].[Registration] r  WITH (NOLOCK)
            JOIN [dbo].[ServiceUnit]  su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
            WHERE 1=1
              {$typeWhere}
              AND r.RegistrationDate >= ?
              AND r.RegistrationDate <  ?
              {$poliWhere}
              {$keywordWhere}
            GROUP BY
                YEAR(r.RegistrationDate),
                MONTH(r.RegistrationDate),
                DATENAME(MONTH, r.RegistrationDate)
            ORDER BY TahunKunjungan, BulanKunjungan
        SQL, $params);
    }

    /**
     * @return object[]
     */
    private function queryDetail(?string $poli = null, string $registrationType = 'OPR', ?string $keyword = null, int $fromYear = 0): array
    {
        $tahun = now()->year;
        if ($fromYear <= 0) {
            $fromYear = $tahun - 1;
        }
        $fromYear = max(2000, min($fromYear, $tahun));
        $startDate = $fromYear.'-01-01';
        $endDate = ($tahun + 1).'-01-01';
        $typeWhere = ($poli === null && $keyword === null) ? 'AND r.SRRegistrationType = ?' : '';
        $poliWhere = $poli ? 'AND su.ServiceUnitName = ?' : '';
        $keywordWhere = $keyword ? 'AND CHARINDEX(?, su.ServiceUnitName) > 0' : '';

        $params = [];
        if ($poli === null && $keyword === null) {
            $params[] = $registrationType;
        }
        $params[] = $startDate;
        $params[] = $endDate;
        if ($poli) {
            $params[] = $poli;
        }
        if ($keyword) {
            $params[] = $keyword;
        }

        return DB::connection('sqlsrv_report')->select(<<<SQL
            SELECT
                YEAR(r.RegistrationDate)  AS TahunKunjungan,
                MONTH(r.RegistrationDate) AS BulanKunjungan,
                su.ServiceUnitName        AS NamaPoli,
                LTRIM(RTRIM(COALESCE(
                    NULLIF(pt.FullName, ''),
                    RTRIM(COALESCE(pt.FirstName,'') + ' ' + COALESCE(pt.MiddleName,'') + ' ' + COALESCE(pt.LastName,''))
                )))                       AS NamaPasien,
                pm.ParamedicName          AS NamaDokter,
                CONVERT(VARCHAR(10), r.RegistrationDate, 103) AS TglKunjungan,
                COALESCE(g.GuarantorName, 'UMUM') AS Penjamin
            FROM [dbo].[Registration] r  WITH (NOLOCK)
            JOIN [dbo].[ServiceUnit]  su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
            JOIN [dbo].[Patient]      pt WITH (NOLOCK) ON pt.PatientID     = r.PatientID
            JOIN [dbo].[Paramedic]    pm WITH (NOLOCK) ON pm.ParamedicID   = r.ParamedicID
            LEFT JOIN [dbo].[Guarantor] g WITH (NOLOCK) ON g.GuarantorID   = r.GuarantorID
            WHERE 1=1
              {$typeWhere}
              AND r.RegistrationDate >= ?
              AND r.RegistrationDate <  ?
              {$poliWhere}
              {$keywordWhere}
            ORDER BY r.RegistrationDate, su.ServiceUnitName, pm.ParamedicName
        SQL, $params);
    }

    public function rawatJalan(Request $request): Response
    {
        $tahun = now()->year;
        $fromYear = (int) ($request->input('fromYear') ?: $tahun - 1);
        $fromYear = max(2000, min($fromYear, $tahun));
        $years = range($fromYear, $tahun);

        return Inertia::render('DownloadReport/RawatJalan', [
            'tahun' => $tahun,
            'fromYear' => $fromYear,
            'years' => $years,
            // Deferred: halaman langsung tampil, data load asinkron
            'rekapData' => Inertia::defer(function () use ($tahun, $fromYear) {
                try {
                    $cacheKey = 'rawat_jalan_rekap_'.$tahun.'_from_'.$fromYear;
                    $rows = Cache::remember($cacheKey, 3600, fn () => array_map(fn ($r) => (array) $r, $this->queryRekap(null, 'OPR', $fromYear)));

                    return ['rows' => $rows, 'error' => null];
                } catch (Throwable $e) {
                    $msg = $e->getMessage();

                    return [
                        'rows' => [],
                        'error' => str_contains($msg, 'not supported') || str_contains($msg, 'could not find driver')
                            ? 'Driver PHP SQL Server (pdo_sqlsrv) belum terpasang di server ini. Hubungi administrator untuk menginstalnya.'
                            : 'Tidak dapat terhubung ke database TARAKAN: '.$msg,
                    ];
                }
            }),
            'poliList' => Inertia::defer(fn () => $this->queryPoliList('OPR'), rescue: true),
        ]);
    }

    public function exportRawatJalan(Request $request): BinaryFileResponse
    {
        $tahun = now()->year;
        $fromYear = (int) ($request->input('fromYear') ?: $tahun - 1);
        $fromYear = max(2000, min($fromYear, $tahun));
        $selectedPoli = $request->input('poli') ?: null;
        $keyword = $request->input('q') ?: null;

        try {
            $rekap = $this->queryRekapBulanan($selectedPoli, 'OPR', $keyword, $fromYear);
            $detail = ($selectedPoli || $keyword) ? $this->queryDetail($selectedPoli, 'OPR', $keyword, $fromYear) : [];
        } catch (Throwable $e) {
            abort(503, 'Database TARAKAN tidak tersedia: '.$e->getMessage());
        }

        return $this->buildExcel($rekap, $detail, 'rawat-jalan', $selectedPoli ?? $keyword, $fromYear);
    }

    public function rawatInap(Request $request): Response
    {
        $tahun = now()->year;
        $fromYear = (int) ($request->input('fromYear') ?: $tahun - 1);
        $fromYear = max(2000, min($fromYear, $tahun));
        $years = range($fromYear, $tahun);

        return Inertia::render('DownloadReport/RawatInap', [
            'tahun' => $tahun,
            'fromYear' => $fromYear,
            'years' => $years,
            // Deferred: halaman langsung tampil, data load asinkron
            'rekapData' => Inertia::defer(function () use ($tahun, $fromYear) {
                try {
                    $cacheKey = 'rawat_inap_rekap_'.$tahun.'_from_'.$fromYear;
                    $rows = Cache::remember($cacheKey, 3600, fn () => array_map(fn ($r) => (array) $r, $this->queryRekap(null, 'IPR', $fromYear)));

                    return ['rows' => $rows, 'error' => null];
                } catch (Throwable $e) {
                    $msg = $e->getMessage();

                    return [
                        'rows' => [],
                        'error' => str_contains($msg, 'not supported') || str_contains($msg, 'could not find driver')
                            ? 'Driver PHP SQL Server (pdo_sqlsrv) belum terpasang di server ini. Hubungi administrator untuk menginstalnya.'
                            : 'Tidak dapat terhubung ke database TARAKAN: '.$msg,
                    ];
                }
            }),
            'poliList' => Inertia::defer(fn () => $this->queryPoliList('IPR'), rescue: true),
        ]);
    }

    public function exportRawatInap(Request $request): BinaryFileResponse
    {
        $tahun = now()->year;
        $fromYear = (int) ($request->input('fromYear') ?: $tahun - 1);
        $fromYear = max(2000, min($fromYear, $tahun));
        $selectedPoli = $request->input('poli') ?: null;
        $keyword = $request->input('q') ?: null;

        try {
            $rekap = $this->queryRekapBulanan($selectedPoli, 'IPR', $keyword, $fromYear);
            $detail = ($selectedPoli || $keyword) ? $this->queryDetail($selectedPoli, 'IPR', $keyword, $fromYear) : [];
        } catch (Throwable $e) {
            abort(503, 'Database TARAKAN tidak tersedia: '.$e->getMessage());
        }

        return $this->buildExcel($rekap, $detail, 'rawat-inap', $selectedPoli ?? $keyword, $fromYear);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Billing NonBPJS
    // ─────────────────────────────────────────────────────────────────────────

    public function billingNonBpjs(Request $request): Response
    {
        $tahun = (int) ($request->input('tahun') ?: now()->year);
        $bulan = $request->filled('bulan') ? (int) $request->input('bulan') : null;
        $page = max(1, (int) ($request->input('page') ?: 1));
        $q = $request->input('q') ?: null;

        // Cache key includes search term so filtered cards are cached separately.
        // Shorter TTL for search results (5 min) vs full period (1 hour).
        $cacheKey = 'billing_non_bpjs_cards_'.$tahun.'_'.($bulan ?? 'all').($q ? '_'.md5($q) : '');
        $cacheTtl = $q ? 300 : 3600;

        $itemsCacheKey = 'billing_items_'.$tahun.'_'.($bulan ?? 'all').'_p'.$page.($q ? '_'.md5($q) : '');
        $itemsCacheTtl = $q ? 60 : 300;

        return Inertia::render('DownloadReport/BillingNonBpjs', [
            'tahun' => $tahun,
            'bulan' => $bulan,
            'cards' => Inertia::defer(function () use ($cacheKey, $cacheTtl, $tahun, $bulan, $q) {
                return Cache::remember($cacheKey, $cacheTtl, function () use ($tahun, $bulan, $q) {
                    return $this->buildBillingCards($tahun, $bulan, $q);
                });
            }, rescue: true),
            'tableData' => Inertia::defer(function () use ($itemsCacheKey, $itemsCacheTtl, $tahun, $bulan, $page, $q) {
                try {
                    $items = Cache::remember($itemsCacheKey, $itemsCacheTtl, fn () => array_map(fn ($r) => (array) $r, $this->queryBillingItems($tahun, $bulan, $page, $q)));
                    $total = count($items) > 0 ? (int) ($items[0]['TotalCount'] ?? 0) : 0;

                    return [
                        'items' => $items,
                        'pagination' => [
                            'total' => $total,
                            'perPage' => 10,
                            'currentPage' => $page,
                            'lastPage' => max(1, (int) ceil($total / 10)),
                        ],
                        'error' => null,
                    ];
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $error = str_contains($msg, 'not supported') || str_contains($msg, 'could not find driver')
                        ? 'Driver PHP SQL Server (pdo_sqlsrv) belum terpasang di server ini. Hubungi administrator untuk menginstalnya.'
                        : 'Tidak dapat terhubung ke database TARAKAN: '.$msg;

                    return [
                        'items' => [],
                        'pagination' => ['total' => 0, 'perPage' => 10, 'currentPage' => $page, 'lastPage' => 1],
                        'error' => $error,
                    ];
                }
            }),
        ]);
    }

    public function exportBillingNonBpjs(Request $request): BinaryFileResponse
    {
        $tahun = (int) ($request->input('tahun') ?: now()->year);
        $bulan = $request->filled('bulan') ? (int) $request->input('bulan') : null;

        try {
            $items = $this->queryBillingExcel($tahun, $bulan);
        } catch (Throwable $e) {
            abort(503, 'Database TARAKAN tidak tersedia: '.$e->getMessage());
        }

        return $this->buildBillingExcel($items, $tahun, $bulan);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBillingCards(int $tahun, ?int $bulan, ?string $q = null): array
    {
        [$startDate, $endDate] = $this->billingDateRange($tahun, $bulan);

        $searchJoins = '';
        $searchWhere = '';
        $params = [$startDate, $endDate];

        if ($q) {
            $searchJoins = <<<'SQL'
                LEFT JOIN [dbo].[ServiceUnit] su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
                LEFT JOIN [dbo].[Paramedic]   pm WITH (NOLOCK) ON pm.ParamedicID   = r.ParamedicID
            SQL;
            $searchWhere = <<<'SQL'
                AND (
                    CHARINDEX(?, r.RegistrationNo COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, p.MedicalNo   COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, ISNULL(p.FullName, RTRIM(LTRIM(
                        ISNULL(p.FirstName,'') +
                        CASE WHEN ISNULL(p.MiddleName,'') != '' THEN ' ' + p.MiddleName ELSE '' END +
                        CASE WHEN ISNULL(p.LastName,  '') != '' THEN ' ' + p.LastName   ELSE '' END
                    ))) COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, g.GuarantorName    COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, pm.ParamedicName   COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, su.ServiceUnitName COLLATE Latin1_General_CI_AS) > 0
                )
            SQL;
            array_push($params, $q, $q, $q, $q, $q, $q);
        }

        $result = DB::connection('sqlsrv_report')->select(<<<SQL
            SELECT
                COUNT(*)                               AS TotalKunjungan,
                ISNULL(SUM(ib_agg.TagihanMitra),   0) AS TotalTagihanMitra,
                ISNULL(SUM(ib_agg.TagihanTunai),   0) AS TotalTagihanTunai,
                ISNULL(SUM(ABS(r.RemainingAmount)), 0) AS TotalTagihanAktual
            FROM [dbo].[Registration] r  WITH (NOLOCK)
            CROSS APPLY (VALUES (
                CASE
                    WHEN r.RegistrationNo LIKE 'REG/__/%'
                         AND TRY_CONVERT(DATE, '20' + SUBSTRING(r.RegistrationNo, 8, 6), 112) IS NOT NULL
                    THEN TRY_CONVERT(DATE, '20' + SUBSTRING(r.RegistrationNo, 8, 6), 112)
                    ELSE CAST(r.RegistrationDate AS DATE)
                END
            )) AS ed(EffectiveDate)
            INNER JOIN [dbo].[Patient]   p  WITH (NOLOCK) ON p.PatientID   = r.PatientID
            INNER JOIN [dbo].[Guarantor] g  WITH (NOLOCK) ON g.GuarantorID = r.GuarantorID
            {$searchJoins}
            LEFT JOIN (
                SELECT RegistrationNo,
                       SUM(GuarantorAmount) AS TagihanMitra,
                       SUM(PatientAmount)   AS TagihanTunai
                FROM [dbo].[IntermBill] WITH (NOLOCK)
                WHERE IsVoid = 0
                GROUP BY RegistrationNo
            ) ib_agg ON ib_agg.RegistrationNo = r.RegistrationNo
            WHERE r.IsVoid = 0
              AND ed.EffectiveDate BETWEEN ? AND ?
              {$searchWhere}
        SQL, $params);

        $row = $result[0] ?? null;

        return [
            'totalKunjungan' => (int) ($row?->TotalKunjungan ?? 0),
            'totalTagihanMitra' => (float) ($row?->TotalTagihanMitra ?? 0),
            'totalTagihanTunai' => (float) ($row?->TotalTagihanTunai ?? 0),
            'totalTagihanAktual' => (float) ($row?->TotalTagihanAktual ?? 0),
        ];
    }

    /** @return array{string, string} */
    private function billingDateRange(int $tahun, ?int $bulan): array
    {
        if ($bulan) {
            $start = sprintf('%04d-%02d-01', $tahun, $bulan);

            return [$start, date('Y-m-t', strtotime($start))];
        }

        return [sprintf('%04d-01-01', $tahun), sprintf('%04d-12-31', $tahun)];
    }

    /**
     * Returns paginated rows that also include TotalCount (window count).
     *
     * @return object[]
     */
    private function queryBillingItems(int $tahun, ?int $bulan, int $page, ?string $q): array
    {
        [$startDate, $endDate] = $this->billingDateRange($tahun, $bulan);
        $offset = ($page - 1) * 10;

        $searchWhere = $q
            ? "AND (
                    CHARINDEX(?, r.RegistrationNo COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, p.MedicalNo   COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, ISNULL(p.FullName, RTRIM(LTRIM(
                        ISNULL(p.FirstName,'') +
                        CASE WHEN ISNULL(p.MiddleName,'') != '' THEN ' ' + p.MiddleName ELSE '' END +
                        CASE WHEN ISNULL(p.LastName,  '') != '' THEN ' ' + p.LastName   ELSE '' END
                    ))) COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, g.GuarantorName   COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, pm.ParamedicName  COLLATE Latin1_General_CI_AS) > 0
                    OR CHARINDEX(?, su.ServiceUnitName COLLATE Latin1_General_CI_AS) > 0
                )"
            : '';

        // Pre-filter on indexed RegistrationDate (±31 day buffer) so SQL Server
        // can use the index before evaluating the CROSS APPLY date expression.
        $preStart = date('Y-m-d', strtotime($startDate.' -31 days'));
        $preEnd = date('Y-m-d', strtotime($endDate.' +31 days'));

        $params = [$preStart, $preEnd, $startDate, $endDate];
        if ($q) {
            array_push($params, $q, $q, $q, $q, $q, $q);
        }
        $params[] = $offset;

        return DB::connection('sqlsrv_report')->select(<<<SQL
            WITH Base AS (
                SELECT
                    r.RegistrationNo,
                    ed.EffectiveDate,
                    CONVERT(VARCHAR(10), ed.EffectiveDate, 103)    AS Tanggal,
                    CONVERT(VARCHAR(8),  r.RegistrationTime, 108)  AS Jam,
                    p.MedicalNo                                    AS NoRM,
                    ISNULL(p.FullName, RTRIM(LTRIM(
                        ISNULL(p.FirstName, '') +
                        CASE WHEN ISNULL(p.MiddleName, '') != '' THEN ' ' + p.MiddleName ELSE '' END +
                        CASE WHEN ISNULL(p.LastName,   '') != '' THEN ' ' + p.LastName   ELSE '' END
                    )))                                            AS NamaPasien,
                    CASE p.Sex
                        WHEN 'M' THEN 'Laki-laki'
                        WHEN 'F' THEN 'Perempuan'
                        ELSE ISNULL(p.Sex, '')
                    END                                            AS JenisKelamin,
                    g.GuarantorName                                AS Jaminan,
                    CASE g.SRGuarantorType
                        WHEN '00' THEN 'UMUM (PRIBADI)'
                        WHEN '01' THEN 'ASURANSI SWASTA'
                        WHEN '02' THEN 'PERUSAHAAN / KONTRAK'
                        WHEN '05' THEN 'RUMAH SAKIT'
                        WHEN '09' THEN 'BPJS'
                        WHEN '10' THEN 'KEMENTERIAN KESEHATAN'
                        WHEN '11' THEN 'DINAS KESEHATAN'
                        ELSE 'LAINNYA'
                    END                                            AS KategoriJaminan,
                    ISNULL(su.ServiceUnitName, '')                 AS UnitLayanan,
                    CASE WHEN su.ServiceUnitName LIKE '%CENDANA%'
                         THEN 'POLI CENDANA' ELSE 'LUAR POLI CENDANA'
                    END                                            AS Lokasi,
                    ISNULL(pm.ParamedicName, '')                   AS Dokter,
                    r.PlavonAmount                                 AS Plafond,
                    ISNULL(SUM(ib.GuarantorAmount), 0)             AS TagihanMitra,
                    ISNULL(SUM(ib.PatientAmount),   0)             AS TagihanTunai,
                    ABS(r.RemainingAmount)                         AS TotalTagihanAktual,
                    r.PlavonAmount - ISNULL(SUM(ib.GuarantorAmount), 0) AS SisaTagihan
                FROM [dbo].[Registration] r  WITH (NOLOCK)
                CROSS APPLY (VALUES (
                    CASE
                        WHEN r.RegistrationNo LIKE 'REG/__/%'
                             AND TRY_CONVERT(DATE, '20' + SUBSTRING(r.RegistrationNo, 8, 6), 112) IS NOT NULL
                        THEN TRY_CONVERT(DATE, '20' + SUBSTRING(r.RegistrationNo, 8, 6), 112)
                        ELSE CAST(r.RegistrationDate AS DATE)
                    END
                )) AS ed(EffectiveDate)
                INNER JOIN [dbo].[Patient]     p  WITH (NOLOCK) ON p.PatientID      = r.PatientID
                INNER JOIN [dbo].[Guarantor]   g  WITH (NOLOCK) ON g.GuarantorID    = r.GuarantorID
                LEFT  JOIN [dbo].[ServiceUnit] su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
                LEFT  JOIN [dbo].[Paramedic]   pm WITH (NOLOCK) ON pm.ParamedicID   = r.ParamedicID
                LEFT  JOIN [dbo].[IntermBill]  ib WITH (NOLOCK) ON ib.RegistrationNo = r.RegistrationNo
                                                                AND ib.IsVoid = 0
                WHERE r.IsVoid = 0
                  AND r.RegistrationDate BETWEEN ? AND ?
                  AND ed.EffectiveDate BETWEEN ? AND ?
                  {$searchWhere}
                GROUP BY
                    r.RegistrationNo, r.RegistrationTime,
                    ed.EffectiveDate,
                    p.MedicalNo, p.FullName, p.FirstName, p.MiddleName, p.LastName, p.Sex,
                    g.GuarantorName, g.SRGuarantorType,
                    su.ServiceUnitName, pm.ParamedicName,
                    r.PlavonAmount, r.RemainingAmount
            )
            SELECT
                RegistrationNo, Tanggal, Jam, NoRM, NamaPasien, JenisKelamin,
                Jaminan, KategoriJaminan, UnitLayanan, Lokasi, Dokter, Plafond,
                TagihanMitra, TagihanTunai, TotalTagihanAktual, SisaTagihan,
                COUNT(*) OVER() AS TotalCount
            FROM Base
            ORDER BY EffectiveDate DESC, RegistrationNo
            OFFSET ? ROWS FETCH NEXT 10 ROWS ONLY
        SQL, $params);
    }

    /**
     * @return object[]
     */
    private function queryBillingExcel(int $tahun, ?int $bulan): array
    {
        [$startDate, $endDate] = $this->billingDateRange($tahun, $bulan);

        return DB::connection('sqlsrv_report')->select(<<<'SQL'
            WITH Base AS (
                SELECT
                    r.RegistrationNo,
                    ed.EffectiveDate,
                    CONVERT(VARCHAR(10), ed.EffectiveDate, 103)    AS Tanggal,
                    CONVERT(VARCHAR(8),  r.RegistrationTime, 108)  AS Jam,
                    p.MedicalNo                                    AS NoRM,
                    ISNULL(p.FullName, RTRIM(LTRIM(
                        ISNULL(p.FirstName, '') +
                        CASE WHEN ISNULL(p.MiddleName, '') != '' THEN ' ' + p.MiddleName ELSE '' END +
                        CASE WHEN ISNULL(p.LastName,   '') != '' THEN ' ' + p.LastName   ELSE '' END
                    )))                                            AS NamaPasien,
                    CASE p.Sex
                        WHEN 'M' THEN 'Laki-laki'
                        WHEN 'F' THEN 'Perempuan'
                        ELSE ISNULL(p.Sex, '')
                    END                                            AS JenisKelamin,
                    g.GuarantorName                                AS Jaminan,
                    CASE g.SRGuarantorType
                        WHEN '00' THEN 'UMUM (PRIBADI)'
                        WHEN '01' THEN 'ASURANSI SWASTA'
                        WHEN '02' THEN 'PERUSAHAAN / KONTRAK'
                        WHEN '05' THEN 'RUMAH SAKIT'
                        WHEN '09' THEN 'BPJS'
                        WHEN '10' THEN 'KEMENTERIAN KESEHATAN'
                        WHEN '11' THEN 'DINAS KESEHATAN'
                        ELSE 'LAINNYA'
                    END                                            AS KategoriJaminan,
                    ISNULL(su.ServiceUnitName, '')                 AS UnitLayanan,
                    CASE WHEN su.ServiceUnitName LIKE '%CENDANA%'
                         THEN 'POLI CENDANA' ELSE 'LUAR POLI CENDANA'
                    END                                            AS Lokasi,
                    ISNULL(pm.ParamedicName, '')                   AS Dokter,
                    r.PlavonAmount                                 AS Plafond,
                    ISNULL(SUM(ib.GuarantorAmount), 0)             AS TagihanMitra,
                    ISNULL(SUM(ib.PatientAmount),   0)             AS TagihanTunai,
                    ABS(r.RemainingAmount)                         AS TotalTagihanAktual,
                    r.PlavonAmount - ISNULL(SUM(ib.GuarantorAmount), 0) AS SisaTagihan
                FROM [dbo].[Registration] r  WITH (NOLOCK)
                CROSS APPLY (VALUES (
                    CASE
                        WHEN r.RegistrationNo LIKE 'REG/__/%'
                             AND TRY_CONVERT(DATE, '20' + SUBSTRING(r.RegistrationNo, 8, 6), 112) IS NOT NULL
                        THEN TRY_CONVERT(DATE, '20' + SUBSTRING(r.RegistrationNo, 8, 6), 112)
                        ELSE CAST(r.RegistrationDate AS DATE)
                    END
                )) AS ed(EffectiveDate)
                INNER JOIN [dbo].[Patient]     p  WITH (NOLOCK) ON p.PatientID      = r.PatientID
                INNER JOIN [dbo].[Guarantor]   g  WITH (NOLOCK) ON g.GuarantorID    = r.GuarantorID
                LEFT  JOIN [dbo].[ServiceUnit] su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
                LEFT  JOIN [dbo].[Paramedic]   pm WITH (NOLOCK) ON pm.ParamedicID   = r.ParamedicID
                LEFT  JOIN [dbo].[IntermBill]  ib WITH (NOLOCK) ON ib.RegistrationNo = r.RegistrationNo
                                                                AND ib.IsVoid = 0
                WHERE r.IsVoid = 0
                  AND ed.EffectiveDate BETWEEN ? AND ?
                GROUP BY
                    r.RegistrationNo, r.RegistrationTime,
                    ed.EffectiveDate,
                    p.MedicalNo, p.FullName, p.FirstName, p.MiddleName, p.LastName, p.Sex,
                    g.GuarantorName, g.SRGuarantorType,
                    su.ServiceUnitName, pm.ParamedicName,
                    r.PlavonAmount, r.RemainingAmount
            )
            SELECT
                RegistrationNo, Tanggal, Jam, NoRM, NamaPasien, JenisKelamin,
                Jaminan, KategoriJaminan, UnitLayanan, Lokasi, Dokter, Plafond,
                TagihanMitra, TagihanTunai, TotalTagihanAktual, SisaTagihan
            FROM Base
            ORDER BY EffectiveDate DESC, RegistrationNo
        SQL, [$startDate, $endDate]);
    }

    /**
     * @param  object[]  $items
     */
    private function buildBillingExcel(array $items, int $tahun, ?int $bulan): BinaryFileResponse
    {
        ini_set('memory_limit', '1024M');

        $spreadsheet = new Spreadsheet;

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

        $bulanId = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $sheetTitle = $bulan ? $bulanId[$bulan].' '.$tahun : (string) $tahun;

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        $headers = [
            'No', 'No. Registrasi', 'Tanggal', 'Jam',
            'No. RM', 'Nama Pasien', 'Jenis Kelamin',
            'Jaminan', 'Kategori Jaminan', 'Unit Layanan', 'Lokasi', 'Dokter',
            'Plafond', 'Tagihan Mitra', 'Tagihan Tunai', 'Total Tagihan', 'Sisa Tagihan',
        ];

        $colCount = count($headers); // 17
        $lastCol = chr(64 + $colCount); // 'Q'

        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray($headerStyle);

        foreach ($items as $ri => $row) {
            $r = $ri + 2;
            $col = 1;
            $sheet->setCellValue([$col++, $r], $ri + 1);
            $sheet->setCellValue([$col++, $r], $row->RegistrationNo);
            $sheet->setCellValue([$col++, $r], $row->Tanggal);
            $sheet->setCellValue([$col++, $r], $row->Jam);
            $sheet->setCellValue([$col++, $r], $row->NoRM);
            $sheet->setCellValue([$col++, $r], $row->NamaPasien);
            $sheet->setCellValue([$col++, $r], $row->JenisKelamin);
            $sheet->setCellValue([$col++, $r], $row->Jaminan);
            $sheet->setCellValue([$col++, $r], $row->KategoriJaminan);
            $sheet->setCellValue([$col++, $r], $row->UnitLayanan);
            $sheet->setCellValue([$col++, $r], $row->Lokasi);
            $sheet->setCellValue([$col++, $r], $row->Dokter);
            $sheet->setCellValue([$col++, $r], (float) $row->Plafond);
            $sheet->setCellValue([$col++, $r], (float) $row->TagihanMitra);
            $sheet->setCellValue([$col++, $r], (float) $row->TagihanTunai);
            $sheet->setCellValue([$col++, $r], (float) $row->TotalTagihanAktual);
            $sheet->setCellValue([$col++, $r], (float) $row->SisaTagihan);
        }

        $totalRows = count($items);
        if ($totalRows > 0) {
            $sheet->getStyle("A2:{$lastCol}".($totalRows + 1))->applyFromArray($borderStyle);
            // Numeric cols: M(13)=Plafond, N(14)=Tagihan Mitra, O(15)=Tagihan Tunai,
            //               P(16)=Total Tagihan, Q(17)=Sisa Tagihan
            foreach ([13, 14, 15, 16, 17] as $numCol) {
                $c = chr(64 + $numCol);
                $sheet->getStyle("{$c}2:{$c}".($totalRows + 1))->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension(chr(64 + $i))->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $suffix = $bulan ? '-'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT) : '';
        $filename = "billing-non-bpjs-{$tahun}{$suffix}.xlsx";
        $writer = new Xlsx($spreadsheet);

        $tmpFile = tempnam(sys_get_temp_dir(), 'xl_');
        $writer->save($tmpFile);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  object[]  $rekap
     * @param  object[]  $detail
     */
    private function buildExcel(array $rekap, array $detail, string $jenis, ?string $selectedPoli, int $fromYear = 0): BinaryFileResponse
    {
        ini_set('memory_limit', '1024M');

        $tahun = now()->year;
        if ($fromYear <= 0) {
            $fromYear = $tahun - 1;
        }
        $spreadsheet = new Spreadsheet;

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

        // ── Sheet 1: Rekap Bulanan ──────────────────────────────────
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Rekap');

        $labelUnit = $jenis === 'rawat-inap' ? 'Nama Ruangan' : 'Nama Poli';
        $rekapHeaders = ['No', 'Bulan', 'Tahun', 'Jumlah Pasien'];

        foreach ($rekapHeaders as $i => $h) {
            $sheet1->setCellValue([$i + 1, 1], $h);
        }
        $sheet1->getStyle('A1:D1')->applyFromArray($headerStyle);

        foreach ($rekap as $ri => $row) {
            $r = $ri + 2;
            $sheet1->setCellValue([1, $r], $ri + 1);
            $sheet1->setCellValue([2, $r], $row->NamaBulan);
            $sheet1->setCellValue([3, $r], (int) $row->TahunKunjungan);
            $sheet1->setCellValue([4, $r], (int) $row->JumlahPasien);
        }

        $totalRekap = count($rekap);
        if ($totalRekap > 0) {
            $sheet1->getStyle('A2:D'.($totalRekap + 1))->applyFromArray($borderStyle);
            $sheet1->getStyle('D2:D'.($totalRekap + 1))->getNumberFormat()->setFormatCode('#,##0');

            $totalRow = $totalRekap + 2;
            $sumTotal = array_sum(array_map(fn ($r) => (int) $r->JumlahPasien, $rekap));
            $sheet1->setCellValue([1, $totalRow], '');
            $sheet1->setCellValue([2, $totalRow], 'TOTAL');
            $sheet1->setCellValue([3, $totalRow], '');
            $sheet1->setCellValue([4, $totalRow], $sumTotal);
            $sheet1->getStyle("A{$totalRow}:D{$totalRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDCE6F1']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $sheet1->getStyle("D{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Per-month sheets ────────────────────────────────────────
        /** @var array<string, object[]> $grouped */
        $grouped = [];
        foreach ($detail as $row) {
            $key = $row->TahunKunjungan.'-'.str_pad((string) $row->BulanKunjungan, 2, '0', STR_PAD_LEFT);
            $grouped[$key][] = $row;
        }
        ksort($grouped);

        $bulanId = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $detailHeaders = ['No', $labelUnit, 'Nama Pasien', 'Nama Dokter', 'Tgl Kunjungan', 'Penjamin'];

        foreach ($grouped as $key => $rows) {
            [$thn, $bln] = explode('-', $key);
            $sheetTitle = $bulanId[(int) $bln].' '.$thn;

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);

            foreach ($detailHeaders as $i => $h) {
                $sheet->setCellValue([$i + 1, 1], $h);
            }
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            foreach ($rows as $ri => $row) {
                $r = $ri + 2;
                $sheet->setCellValue([1, $r], $ri + 1);
                $sheet->setCellValue([2, $r], $row->NamaPoli);
                $sheet->setCellValue([3, $r], $row->NamaPasien);
                $sheet->setCellValue([4, $r], $row->NamaDokter);
                $sheet->setCellValue([5, $r], $row->TglKunjungan);
                $sheet->setCellValue([6, $r], $row->Penjamin);
            }

            $totalRows = count($rows);
            if ($totalRows > 0) {
                $sheet->getStyle('A2:F'.($totalRows + 1))->applyFromArray($borderStyle);
            }

            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $suffix = $selectedPoli ? '-'.str_replace(' ', '_', $selectedPoli) : '';
        $yearRange = $fromYear < $tahun ? "{$fromYear}-{$tahun}" : (string) $tahun;
        $filename = "laporan-kunjungan-{$jenis}-{$yearRange}{$suffix}.xlsx";
        $writer = new Xlsx($spreadsheet);

        $tmpFile = tempnam(sys_get_temp_dir(), 'xl_');
        $writer->save($tmpFile);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Data Kunjungan Dokter
    // ──────────────────────────────────────────────────────────────────────────

    public function kunjunganDokter(Request $request): Response
    {
        $fromDate = $request->input('fromDate') ?: now()->startOfMonth()->toDateString();
        $toDate = $request->input('toDate') ?: now()->toDateString();
        $page = max(1, (int) ($request->input('page') ?: 1));
        $q = $request->input('q') ?: null;
        $detailId = $request->input('detailId') ?: null;

        $itemsCacheKey = 'kunjungan_items_'.$fromDate.'_'.$toDate.'_p'.$page.($q ? '_'.md5($q) : '');
        $itemsCacheTtl = $q ? 60 : 300;

        return Inertia::render('DownloadReport/KunjunganDokter', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'tableData' => Inertia::defer(function () use ($itemsCacheKey, $itemsCacheTtl, $fromDate, $toDate, $page, $q) {
                try {
                    $items = Cache::remember($itemsCacheKey, $itemsCacheTtl, fn () => array_map(fn ($r) => (array) $r, $this->queryKunjunganItems($fromDate, $toDate, $page, $q)));
                    $total = count($items) > 0 ? (int) ($items[0]['TotalDokter'] ?? 0) : 0;

                    return [
                        'items' => $items,
                        'pagination' => [
                            'total' => $total,
                            'perPage' => 10,
                            'currentPage' => $page,
                            'lastPage' => max(1, (int) ceil($total / 10)),
                        ],
                    ];
                } catch (Throwable $e) {
                    return [
                        'items' => [],
                        'pagination' => ['total' => 0, 'perPage' => 10, 'currentPage' => $page, 'lastPage' => 1],
                    ];
                }
            }),
            'detail' => Inertia::optional(function () use ($detailId, $fromDate, $toDate) {
                if (! $detailId) {
                    return null;
                }

                $rows = $this->queryKunjunganDetail($fromDate, $toDate, $detailId);
                $name = count($rows) > 0 ? ($rows[0]->ParamedicName ?? $detailId) : $detailId;

                return [
                    'paramedicId' => $detailId,
                    'paramedicName' => $name,
                    'rows' => $rows,
                ];
            }),
        ]);
    }

    public function exportKunjunganDokter(Request $request): BinaryFileResponse
    {
        $fromDate = $request->input('fromDate') ?: now()->startOfMonth()->toDateString();
        $toDate = $request->input('toDate') ?: now()->toDateString();
        $paramedicId = $request->input('paramedicId') ?: null;

        $allMonthlyByDoctor = [];
        $allPatientsByDoctor = [];

        try {
            if ($paramedicId) {
                $items = $this->queryKunjunganDetail($fromDate, $toDate, $paramedicId);
                $patients = $this->queryKunjunganDetailPatients($fromDate, $toDate, $paramedicId);
            } else {
                $items = $this->queryKunjunganExcel($fromDate, $toDate);
                $patients = [];

                foreach ($this->queryKunjunganAllMonthly($fromDate, $toDate) as $row) {
                    $allMonthlyByDoctor[$row->ParamedicID][] = $row;
                }
                foreach ($this->queryKunjunganAllPatients($fromDate, $toDate) as $row) {
                    $allPatientsByDoctor[$row->ParamedicID][] = $row;
                }
            }
        } catch (Throwable $e) {
            abort(503, 'Database TARAKAN tidak tersedia: '.$e->getMessage());
        }

        return $this->buildKunjunganExcel($items, $patients, $fromDate, $toDate, $paramedicId, $allMonthlyByDoctor, $allPatientsByDoctor);
    }

    /**
     * Returns paginated rows that also include TotalDokter (window count),
     * eliminating the need for a separate COUNT query.
     *
     * @return object[]
     */
    private function queryKunjunganItems(string $fromDate, string $toDate, int $page, ?string $q): array
    {
        $offset = ($page - 1) * 10;
        $searchWhere = $q
            ? 'AND CHARINDEX(?, p.ParamedicName COLLATE Latin1_General_CI_AS) > 0'
            : '';
        $params = [$fromDate, $toDate];
        if ($q) {
            $params[] = $q;
        }
        $params[] = $offset;

        return DB::connection('sqlsrv_report')->select(<<<SQL
            WITH Aggregated AS (
                SELECT
                    p.ParamedicID,
                    p.ParamedicName,
                    SUM(CASE WHEN r.SRRegistrationType = 'OPR' THEN 1 ELSE 0 END) AS JumlahRJ,
                    SUM(CASE WHEN r.SRRegistrationType = 'IPR' THEN 1 ELSE 0 END) AS JumlahRI,
                    COUNT(r.RegistrationNo)                                         AS JumlahPasien
                FROM [dbo].[Registration] r WITH (NOLOCK)
                JOIN [dbo].[Paramedic]    p  WITH (NOLOCK) ON p.ParamedicID = r.ParamedicID
                WHERE r.RegistrationDate BETWEEN ? AND ?
                  {$searchWhere}
                GROUP BY p.ParamedicID, p.ParamedicName
            )
            SELECT
                ParamedicID,
                ParamedicName,
                JumlahRJ,
                JumlahRI,
                JumlahPasien,
                COUNT(*) OVER() AS TotalDokter
            FROM Aggregated
            ORDER BY JumlahPasien DESC, ParamedicName
            OFFSET ? ROWS FETCH NEXT 10 ROWS ONLY
        SQL, $params);
    }

    /**
     * @return object[]
     */
    private function queryKunjunganDetail(string $fromDate, string $toDate, string $paramedicId): array
    {
        return DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT
                p.ParamedicName,
                MONTH(r.RegistrationDate)                                          AS bulan,
                DATENAME(MONTH, r.RegistrationDate)                                AS namaBulan,
                YEAR(r.RegistrationDate)                                           AS tahun,
                SUM(CASE WHEN r.SRRegistrationType = 'OPR' THEN 1 ELSE 0 END)     AS JumlahRJ,
                SUM(CASE WHEN r.SRRegistrationType = 'IPR' THEN 1 ELSE 0 END)     AS JumlahRI,
                COUNT(r.RegistrationNo)                                            AS JumlahPasien
            FROM [dbo].[Registration] r WITH (NOLOCK)
            JOIN [dbo].[Paramedic]    p  WITH (NOLOCK) ON p.ParamedicID = r.ParamedicID
            WHERE r.RegistrationDate BETWEEN ? AND ?
              AND p.ParamedicID = ?
            GROUP BY p.ParamedicName,
                     MONTH(r.RegistrationDate),
                     DATENAME(MONTH, r.RegistrationDate),
                     YEAR(r.RegistrationDate)
            ORDER BY YEAR(r.RegistrationDate), MONTH(r.RegistrationDate)
        SQL, [$fromDate, $toDate, $paramedicId]);
    }

    /**
     * @return object[]
     */
    private function queryKunjunganDetailPatients(string $fromDate, string $toDate, string $paramedicId): array
    {
        return DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT
                r.RegistrationNo,
                pt.MedicalNo,
                LTRIM(RTRIM(
                    ISNULL(pt.FirstName, '') +
                    CASE WHEN ISNULL(pt.MiddleName, '') != '' THEN ' ' + pt.MiddleName ELSE '' END +
                    CASE WHEN ISNULL(pt.LastName,   '') != '' THEN ' ' + pt.LastName   ELSE '' END
                ))                                              AS PatientName,
                su.ServiceUnitName,
                g.GuarantorName,
                CASE r.SRRegistrationType
                    WHEN 'OPR' THEN 'Rawat Jalan'
                    WHEN 'IPR' THEN 'Rawat Inap'
                    ELSE r.SRRegistrationType
                END                                             AS JenisKunjungan,
                CONVERT(VARCHAR(10), r.RegistrationDate, 103)  AS RegistrationDate
            FROM [dbo].[Registration] r  WITH (NOLOCK)
            JOIN [dbo].[Patient]      pt WITH (NOLOCK) ON pt.PatientID    = r.PatientID
            JOIN [dbo].[Paramedic]    p  WITH (NOLOCK) ON p.ParamedicID   = r.ParamedicID
            JOIN [dbo].[ServiceUnit]  su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
            JOIN [dbo].[Guarantor]    g  WITH (NOLOCK) ON g.GuarantorID   = r.GuarantorID
            WHERE r.RegistrationDate BETWEEN ? AND ?
              AND p.ParamedicID = ?
            ORDER BY r.RegistrationDate, r.RegistrationNo
        SQL, [$fromDate, $toDate, $paramedicId]);
    }

    /**
     * @return object[]
     */
    private function queryKunjunganExcel(string $fromDate, string $toDate): array
    {
        return DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT
                p.ParamedicID,
                p.ParamedicName,
                SUM(CASE WHEN r.SRRegistrationType = 'OPR' THEN 1 ELSE 0 END) AS JumlahRJ,
                SUM(CASE WHEN r.SRRegistrationType = 'IPR' THEN 1 ELSE 0 END) AS JumlahRI,
                COUNT(r.RegistrationNo)                                        AS JumlahPasien
            FROM [dbo].[Registration] r WITH (NOLOCK)
            JOIN [dbo].[Paramedic]    p  WITH (NOLOCK) ON p.ParamedicID = r.ParamedicID
            WHERE r.RegistrationDate BETWEEN ? AND ?
            GROUP BY p.ParamedicID, p.ParamedicName
            ORDER BY COUNT(r.RegistrationNo) DESC, p.ParamedicName
        SQL, [$fromDate, $toDate]);
    }

    /**
     * Monthly breakdown for ALL doctors — used for the all-doctors Excel export.
     *
     * @return object[]
     */
    private function queryKunjunganAllMonthly(string $fromDate, string $toDate): array
    {
        return DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT
                p.ParamedicID,
                p.ParamedicName,
                MONTH(r.RegistrationDate)                                          AS bulan,
                DATENAME(MONTH, r.RegistrationDate)                                AS namaBulan,
                YEAR(r.RegistrationDate)                                           AS tahun,
                SUM(CASE WHEN r.SRRegistrationType = 'OPR' THEN 1 ELSE 0 END)     AS JumlahRJ,
                SUM(CASE WHEN r.SRRegistrationType = 'IPR' THEN 1 ELSE 0 END)     AS JumlahRI,
                COUNT(r.RegistrationNo)                                            AS JumlahPasien
            FROM [dbo].[Registration] r WITH (NOLOCK)
            JOIN [dbo].[Paramedic]    p  WITH (NOLOCK) ON p.ParamedicID = r.ParamedicID
            WHERE r.RegistrationDate BETWEEN ? AND ?
            GROUP BY p.ParamedicID,
                     p.ParamedicName,
                     MONTH(r.RegistrationDate),
                     DATENAME(MONTH, r.RegistrationDate),
                     YEAR(r.RegistrationDate)
            ORDER BY p.ParamedicName, YEAR(r.RegistrationDate), MONTH(r.RegistrationDate)
        SQL, [$fromDate, $toDate]);
    }

    /**
     * All patient records for ALL doctors — used for the all-doctors Excel export.
     *
     * @return object[]
     */
    private function queryKunjunganAllPatients(string $fromDate, string $toDate): array
    {
        return DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT
                p.ParamedicID,
                r.RegistrationNo,
                pt.MedicalNo,
                LTRIM(RTRIM(
                    ISNULL(pt.FirstName, '') +
                    CASE WHEN ISNULL(pt.MiddleName, '') != '' THEN ' ' + pt.MiddleName ELSE '' END +
                    CASE WHEN ISNULL(pt.LastName,   '') != '' THEN ' ' + pt.LastName   ELSE '' END
                ))                                              AS PatientName,
                su.ServiceUnitName,
                g.GuarantorName,
                CASE r.SRRegistrationType
                    WHEN 'OPR' THEN 'Rawat Jalan'
                    WHEN 'IPR' THEN 'Rawat Inap'
                    ELSE r.SRRegistrationType
                END                                             AS JenisKunjungan,
                CONVERT(VARCHAR(10), r.RegistrationDate, 103)  AS RegistrationDate
            FROM [dbo].[Registration] r  WITH (NOLOCK)
            JOIN [dbo].[Patient]      pt WITH (NOLOCK) ON pt.PatientID    = r.PatientID
            JOIN [dbo].[Paramedic]    p  WITH (NOLOCK) ON p.ParamedicID   = r.ParamedicID
            JOIN [dbo].[ServiceUnit]  su WITH (NOLOCK) ON su.ServiceUnitID = r.ServiceUnitID
            JOIN [dbo].[Guarantor]    g  WITH (NOLOCK) ON g.GuarantorID   = r.GuarantorID
            WHERE r.RegistrationDate BETWEEN ? AND ?
            ORDER BY p.ParamedicName, r.RegistrationDate, r.RegistrationNo
        SQL, [$fromDate, $toDate]);
    }

    /**
     * @param  object[]  $items
     * @param  object[]  $patients
     */
    private function buildKunjunganExcel(
        array $items,
        array $patients,
        string $fromDate,
        string $toDate,
        ?string $paramedicId,
        array $allMonthlyByDoctor = [],
        array $allPatientsByDoctor = [],
    ): BinaryFileResponse {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $spreadsheet = new Spreadsheet;
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

        $sheet = $spreadsheet->getActiveSheet();

        if ($paramedicId) {
            // ── Sheet 1: Rekap Bulanan ──────────────────────────────────────
            $paramedicName = count($items) > 0 ? ($items[0]->ParamedicName ?? $paramedicId) : $paramedicId;
            $sheet->setTitle('Rekap Bulanan');

            $headers1 = ['No', 'Bulan', 'Tahun', 'Rawat Jalan', 'Rawat Inap', 'Total'];
            foreach ($headers1 as $i => $h) {
                $sheet->setCellValue([$i + 1, 1], $h);
            }
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            foreach ($items as $ri => $row) {
                $r = $ri + 2;
                $sheet->setCellValue([1, $r], $ri + 1);
                $sheet->setCellValue([2, $r], $row->namaBulan);
                $sheet->setCellValue([3, $r], (int) $row->tahun);
                $sheet->setCellValue([4, $r], (int) $row->JumlahRJ);
                $sheet->setCellValue([5, $r], (int) $row->JumlahRI);
                $sheet->setCellValue([6, $r], (int) $row->JumlahPasien);
            }

            $totalRows1 = count($items);
            if ($totalRows1 > 0) {
                $sheet->getStyle('A2:F'.($totalRows1 + 1))->applyFromArray($borderStyle);
                $sheet->getStyle('D2:F'.($totalRows1 + 1))->getNumberFormat()->setFormatCode('#,##0');
            }
            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // ── Sheet 2: Detail Pasien ──────────────────────────────────────
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Detail Pasien');

            $headers2 = ['No', 'No. Registrasi', 'No. RM', 'Nama Pasien', 'Poli', 'Jaminan', 'Jenis Kunjungan', 'Tgl Kunjungan'];
            foreach ($headers2 as $i => $h) {
                $sheet2->setCellValue([$i + 1, 1], $h);
            }
            $sheet2->getStyle('A1:H1')->applyFromArray($headerStyle);

            foreach ($patients as $ri => $row) {
                $r = $ri + 2;
                $sheet2->setCellValue([1, $r], $ri + 1);
                $sheet2->setCellValue([2, $r], $row->RegistrationNo);
                $sheet2->setCellValue([3, $r], $row->MedicalNo);
                $sheet2->setCellValue([4, $r], $row->PatientName);
                $sheet2->setCellValue([5, $r], $row->ServiceUnitName);
                $sheet2->setCellValue([6, $r], $row->GuarantorName);
                $sheet2->setCellValue([7, $r], $row->JenisKunjungan);
                $sheet2->setCellValue([8, $r], $row->RegistrationDate);
            }

            $totalRows2 = count($patients);
            if ($totalRows2 > 0) {
                $sheet2->getStyle('A2:H'.($totalRows2 + 1))->applyFromArray($borderStyle);
            }
            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
                $sheet2->getColumnDimension($col)->setAutoSize(true);
            }

            $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $paramedicName ?? $paramedicId);
            $filename = "kunjungan-dokter-{$safeName}-{$fromDate}-{$toDate}.xlsx";
        } else {
            // ── All-doctors summary ─────────────────────────────────────────
            $sheet->setTitle('Data Kunjungan Dokter');
            $headers = ['No', 'Nama Dokter', 'Rawat Jalan', 'Rawat Inap', 'Total'];
            foreach ($headers as $i => $h) {
                $sheet->setCellValue([$i + 1, 1], $h);
            }
            $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

            foreach ($items as $ri => $row) {
                $r = $ri + 2;
                $sheet->setCellValue([1, $r], $ri + 1);
                $sheet->setCellValue([2, $r], $row->ParamedicName);
                $sheet->setCellValue([3, $r], (int) $row->JumlahRJ);
                $sheet->setCellValue([4, $r], (int) $row->JumlahRI);
                $sheet->setCellValue([5, $r], (int) $row->JumlahPasien);
            }

            $totalRows = count($items);
            if ($totalRows > 0) {
                $sheet->getStyle('A2:E'.($totalRows + 1))->applyFromArray($borderStyle);
                $sheet->getStyle('C2:E'.($totalRows + 1))->getNumberFormat()->setFormatCode('#,##0');
            }
            foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // ── Per-doctor sheets (Rekap Bulanan + Detail Pasien) ───────────
            foreach ($items as $docIdx => $docRow) {
                $docId = $docRow->ParamedicID;
                $docName = $docRow->ParamedicName ?? $docId;
                $docNo = $docIdx + 1;

                // Sanitize name for sheet title (Excel: max 31 chars, no []:\/*?)
                $safeName = preg_replace('/[\[\]:*?\/\\\\]/', '', $docName);

                // Sheet: Rekap Bulanan
                $sheetR = $spreadsheet->createSheet();
                $titleR = 'R'.$docNo.'.'.mb_substr($safeName, 0, 28 - strlen((string) $docNo));
                $sheetR->setTitle($titleR);

                $sheetR->setCellValueExplicit([1, 1], 'No', DataType::TYPE_STRING);
                $sheetR->setCellValueExplicit([2, 1], 'Bulan', DataType::TYPE_STRING);
                $sheetR->setCellValueExplicit([3, 1], 'Tahun', DataType::TYPE_STRING);
                $sheetR->setCellValueExplicit([4, 1], 'Rawat Jalan', DataType::TYPE_STRING);
                $sheetR->setCellValueExplicit([5, 1], 'Rawat Inap', DataType::TYPE_STRING);
                $sheetR->setCellValueExplicit([6, 1], 'Total', DataType::TYPE_STRING);
                $sheetR->getStyle('A1:F1')->applyFromArray($headerStyle);

                $monthlyRows = $allMonthlyByDoctor[$docId] ?? [];
                foreach ($monthlyRows as $ri => $mRow) {
                    $r = $ri + 2;
                    $sheetR->setCellValue([1, $r], $ri + 1);
                    $sheetR->setCellValueExplicit([2, $r], (string) $mRow->namaBulan, DataType::TYPE_STRING);
                    $sheetR->setCellValue([3, $r], (int) $mRow->tahun);
                    $sheetR->setCellValue([4, $r], (int) $mRow->JumlahRJ);
                    $sheetR->setCellValue([5, $r], (int) $mRow->JumlahRI);
                    $sheetR->setCellValue([6, $r], (int) $mRow->JumlahPasien);
                }
                if (count($monthlyRows) > 0) {
                    $sheetR->getStyle('A2:F'.(count($monthlyRows) + 1))->applyFromArray($borderStyle);
                    $sheetR->getStyle('D2:F'.(count($monthlyRows) + 1))->getNumberFormat()->setFormatCode('#,##0');
                }
                // Fixed widths avoid autoSize formula evaluation across many sheets
                $sheetR->getColumnDimension('A')->setWidth(6);
                $sheetR->getColumnDimension('B')->setWidth(16);
                $sheetR->getColumnDimension('C')->setWidth(8);
                $sheetR->getColumnDimension('D')->setWidth(14);
                $sheetR->getColumnDimension('E')->setWidth(14);
                $sheetR->getColumnDimension('F')->setWidth(10);

                // Sheet: Detail Pasien
                $sheetP = $spreadsheet->createSheet();
                $titleP = 'P'.$docNo.'.'.mb_substr($safeName, 0, 28 - strlen((string) $docNo));
                $sheetP->setTitle($titleP);

                $sheetP->setCellValueExplicit([1, 1], 'No', DataType::TYPE_STRING);
                $sheetP->setCellValueExplicit([2, 1], 'No. Registrasi', DataType::TYPE_STRING);
                $sheetP->setCellValueExplicit([3, 1], 'No. RM', DataType::TYPE_STRING);
                $sheetP->setCellValueExplicit([4, 1], 'Nama Pasien', DataType::TYPE_STRING);
                $sheetP->setCellValueExplicit([5, 1], 'Poli', DataType::TYPE_STRING);
                $sheetP->setCellValueExplicit([6, 1], 'Jaminan', DataType::TYPE_STRING);
                $sheetP->setCellValueExplicit([7, 1], 'Jenis Kunjungan', DataType::TYPE_STRING);
                $sheetP->setCellValueExplicit([8, 1], 'Tgl Kunjungan', DataType::TYPE_STRING);
                $sheetP->getStyle('A1:H1')->applyFromArray($headerStyle);

                $patientRows = $allPatientsByDoctor[$docId] ?? [];
                foreach ($patientRows as $ri => $pRow) {
                    $r = $ri + 2;
                    $sheetP->setCellValue([1, $r], $ri + 1);
                    $sheetP->setCellValueExplicit([2, $r], (string) $pRow->RegistrationNo, DataType::TYPE_STRING);
                    $sheetP->setCellValueExplicit([3, $r], (string) $pRow->MedicalNo, DataType::TYPE_STRING);
                    $sheetP->setCellValueExplicit([4, $r], (string) $pRow->PatientName, DataType::TYPE_STRING);
                    $sheetP->setCellValueExplicit([5, $r], (string) $pRow->ServiceUnitName, DataType::TYPE_STRING);
                    $sheetP->setCellValueExplicit([6, $r], (string) $pRow->GuarantorName, DataType::TYPE_STRING);
                    $sheetP->setCellValueExplicit([7, $r], (string) $pRow->JenisKunjungan, DataType::TYPE_STRING);
                    $sheetP->setCellValueExplicit([8, $r], (string) $pRow->RegistrationDate, DataType::TYPE_STRING);
                }
                if (count($patientRows) > 0) {
                    $sheetP->getStyle('A2:H'.(count($patientRows) + 1))->applyFromArray($borderStyle);
                }
                // Fixed widths avoid autoSize formula evaluation across many sheets
                $sheetP->getColumnDimension('A')->setWidth(6);
                $sheetP->getColumnDimension('B')->setWidth(18);
                $sheetP->getColumnDimension('C')->setWidth(16);
                $sheetP->getColumnDimension('D')->setWidth(36);
                $sheetP->getColumnDimension('E')->setWidth(22);
                $sheetP->getColumnDimension('F')->setWidth(22);
                $sheetP->getColumnDimension('G')->setWidth(16);
                $sheetP->getColumnDimension('H')->setWidth(14);
            }

            $filename = "kunjungan-dokter-{$fromDate}-{$toDate}.xlsx";
        }

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'xl_');
        $writer->save($tmpFile);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // KUNJUNGAN PASIEN HARI INI
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Query semua kunjungan rawat jalan hari ini.
     *
     * @return array<int,array<string,mixed>>
     */
    private function queryKunjunganPasienHariIni(): array
    {
        $rows = DB::connection('sqlsrv_report')->select(<<<'SQL'
            SELECT
                r.RegistrationNo,
                CONVERT(varchar(10), r.RegistrationDate, 23) AS RegistrationDate,
                CONVERT(varchar(8),  r.RegistrationTime, 108) AS RegistrationTime,
                p.MedicalNo               AS NoRekamMedis,
                LTRIM(RTRIM(
                    ISNULL(p.FirstName, '') + ' ' +
                    ISNULL(p.MiddleName, '') + ' ' +
                    ISNULL(p.LastName, '')
                ))                        AS NamaPasien,
                r.AgeInYear               AS Umur,
                p.Sex                     AS JK,
                su.ServiceUnitName        AS NamaPoli,
                ISNULL(pm.ParamedicName, '-') AS NamaDokter,
                g.GuarantorName           AS Penjamin,
                r.GuarantorCardNo         AS NoKartu,
                r.BpjsSepNo               AS NoSEP,
                CONVERT(varchar(8), a.SlotStartTime, 108) AS JamSlot,
                CASE
                    WHEN a.SlotStartTime IS NOT NULL AND CONVERT(varchar(8), a.SlotStartTime, 108) > '12:00:00' THEN 'SORE'
                    WHEN a.SlotStartTime IS NOT NULL AND CONVERT(varchar(8), a.SlotStartTime, 108) <= '12:00:00' THEN 'PAGI'
                    WHEN r.IsAfterMidday = 1 THEN 'SORE'
                    ELSE 'PAGI'
                END                       AS KeteranganWaktu
            FROM Registration r WITH (NOLOCK)
            LEFT JOIN Patient      p  ON r.PatientID     = p.PatientID
            LEFT JOIN ServiceUnit  su ON r.ServiceUnitID = su.ServiceUnitID
            LEFT JOIN Guarantor    g  ON r.GuarantorID   = g.GuarantorID
            LEFT JOIN Appointment  a  ON r.AppointmentNo = a.AppointmentNo
                                     AND r.AppointmentNo <> ''
            LEFT JOIN Paramedic    pm ON r.ParamedicID   = pm.ParamedicID
            WHERE
                CAST(r.RegistrationDate AS DATE) = CAST(GETDATE() AS DATE)
                AND r.IsVoid             = 0
                AND r.SRRegistrationType = 'OPR'
                AND r.ServiceUnitID     <> 'D2.1.C01'
            ORDER BY
                r.IsAfterMidday ASC,
                r.RegistrationTime ASC
        SQL);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    /** Tentukan biaya berdasarkan nama poli (konsul/lab/radiologi). */
    private function getBiayaPoli(string $namaPoli): int
    {
        $upper = strtoupper($namaPoli);
        if (str_contains($upper, 'LAB') || str_contains($upper, 'LABORATORIUM')) {
            return 10000;
        }
        if (str_contains($upper, 'RADIO') || str_contains($upper, 'RONTGEN') || str_contains($upper, 'RADIOLOGI')) {
            return 15000;
        }

        return 60000; // konsul dokter
    }

    public function kunjunganPasien(): Response
    {
        return Inertia::render('DownloadReport/KunjunganPasien', [
            'tanggal' => now()->translatedFormat('d F Y'),
            'items' => Inertia::defer(function () {
                try {
                    $rows = $this->queryKunjunganPasienHariIni();

                    return ['rows' => $rows, 'error' => null];
                } catch (Throwable $e) {
                    $msg = $e->getMessage();

                    return [
                        'rows' => [],
                        'error' => str_contains($msg, 'could not find driver')
                            ? 'Driver PHP SQL Server (pdo_sqlsrv) belum terpasang.'
                            : 'Tidak dapat terhubung ke database TARAKAN: '.$msg,
                    ];
                }
            }),
        ]);
    }

    public function exportKunjunganPasien(): BinaryFileResponse
    {
        try {
            $rows = $this->queryKunjunganPasienHariIni();
        } catch (Throwable $e) {
            abort(503, 'Database TARAKAN tidak tersedia: '.$e->getMessage());
        }

        $tanggal = now()->format('Y-m-d');
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
        ];
        $borderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kunjungan Pasien');

        // Header info
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'LAPORAN KUNJUNGAN PASIEN RAWAT JALAN — '.$tanggal);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Column headers row 2
        $headers = ['No', 'No. Registrasi', 'No. RM', 'Nama Pasien', 'Umur', 'JK', 'Nama Poli', 'Nama Dokter', 'Penjamin', 'No. Kartu', 'No. SEP', 'Ket. Waktu'];
        foreach ($headers as $ci => $h) {
            $sheet->setCellValueExplicit([$ci + 1, 2], $h, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A2:L2')->applyFromArray($headerStyle);

        foreach ($rows as $i => $row) {
            $r = $i + 3;
            $sheet->setCellValue([1, $r], $i + 1);
            $sheet->setCellValueExplicit([2, $r], (string) ($row['RegistrationNo'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([3, $r], (string) ($row['NoRekamMedis'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([4, $r], (string) ($row['NamaPasien'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue([5, $r], (int) ($row['Umur'] ?? 0));
            $sheet->setCellValueExplicit([6, $r], (string) ($row['JK'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([7, $r], (string) ($row['NamaPoli'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([8, $r], (string) ($row['NamaDokter'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([9, $r], (string) ($row['Penjamin'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([10, $r], (string) ($row['NoKartu'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([11, $r], (string) ($row['NoSEP'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([12, $r], (string) ($row['KeteranganWaktu'] ?? ''), DataType::TYPE_STRING);
        }

        if (count($rows) > 0) {
            $sheet->getStyle('A3:L'.(count($rows) + 2))->applyFromArray($borderStyle);
        }

        foreach (['A' => 5, 'B' => 18, 'C' => 16, 'D' => 34, 'E' => 7, 'F' => 5, 'G' => 26, 'H' => 28, 'I' => 22, 'J' => 18, 'K' => 16, 'L' => 12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension(2)->setRowHeight(30);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'xl_');
        $writer->save($tmpFile);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($tmpFile, "kunjungan-pasien-{$tanggal}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPayslipSore(): BinaryFileResponse
    {
        try {
            $allRows = $this->queryKunjunganPasienHariIni();
        } catch (Throwable $e) {
            abort(503, 'Database TARAKAN tidak tersedia: '.$e->getMessage());
        }

        // Filter hanya SORE
        $soreRows = array_values(array_filter($allRows, fn ($r) => ($r['KeteranganWaktu'] ?? '') === 'SORE'));

        // Kelompokkan per dokter
        $byDoctor = [];
        foreach ($soreRows as $row) {
            $dokter = trim($row['NamaDokter'] ?? '-');
            if ($dokter === '' || $dokter === '-') {
                $dokter = 'Tanpa Dokter';
            }
            $byDoctor[$dokter][] = $row;
        }
        ksort($byDoctor);

        $tanggal = now()->format('Y-m-d');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
        ];
        $borderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
        ];
        $subtotalStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FE']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ];

        $spreadsheet = new Spreadsheet;

        // ── Sheet ringkasan semua dokter ───────────────────────────────────────
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Ringkasan');

        $summary->mergeCells('A1:G1');
        $summary->setCellValue('A1', 'PAYSLIP KUNJUNGAN SORE — '.$tanggal);
        $summary->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $summaryHeaders = ['No', 'Nama Dokter', 'Jml Pasien', 'Konsul (60.000)', 'Lab (10.000)', 'Radiologi (15.000)', 'Total Pendapatan'];
        foreach ($summaryHeaders as $ci => $h) {
            $summary->setCellValueExplicit([$ci + 1, 2], $h, DataType::TYPE_STRING);
        }
        $summary->getStyle('A2:G2')->applyFromArray($headerStyle);

        $docNo = 0;
        $grandTotal = 0;

        foreach ($byDoctor as $dokterName => $pasienList) {
            $docNo++;

            // Hitung biaya per pasien
            $totalKonsul = 0;
            $totalLab = 0;
            $totalRadiologi = 0;

            foreach ($pasienList as $p) {
                $biaya = $this->getBiayaPoli($p['NamaPoli'] ?? '');
                if ($biaya === 10000) {
                    $totalLab += $biaya;
                } elseif ($biaya === 15000) {
                    $totalRadiologi += $biaya;
                } else {
                    $totalKonsul += $biaya;
                }
            }

            $subtotal = $totalKonsul + $totalLab + $totalRadiologi;
            $grandTotal += $subtotal;

            $sr = $docNo + 2;
            $summary->setCellValue([1, $sr], $docNo);
            $summary->setCellValueExplicit([2, $sr], $dokterName, DataType::TYPE_STRING);
            $summary->setCellValue([3, $sr], count($pasienList));
            $summary->setCellValue([4, $sr], $totalKonsul);
            $summary->setCellValue([5, $sr], $totalLab);
            $summary->setCellValue([6, $sr], $totalRadiologi);
            $summary->setCellValue([7, $sr], $subtotal);

            // Format angka sebagai currency
            $summary->getStyle("D{$sr}:G{$sr}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $summary->getStyle("A{$sr}:G{$sr}")->applyFromArray($borderStyle);

            // ── Sheet detail per dokter ──────────────────────────────────────
            $detailSheet = $spreadsheet->createSheet();
            $safeName = preg_replace('/[^A-Za-z0-9 ]/', '', $dokterName);
            $safeName = mb_substr(trim($safeName), 0, 25);
            $sheetTitle = mb_substr($docNo.'. '.$safeName, 0, 31);
            $detailSheet->setTitle($sheetTitle);

            // Title
            $detailSheet->mergeCells('A1:H1');
            $detailSheet->setCellValue('A1', 'PAYSLIP SORE — '.$dokterName.' — '.$tanggal);
            $detailSheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Header tabel
            $detailHeaders = ['No', 'No. Registrasi', 'No. RM', 'Nama Pasien', 'Nama Poli', 'Jenis Biaya', 'Biaya', 'Jam'];
            foreach ($detailHeaders as $ci => $h) {
                $detailSheet->setCellValueExplicit([$ci + 1, 2], $h, DataType::TYPE_STRING);
            }
            $detailSheet->getStyle('A2:H2')->applyFromArray($headerStyle);

            $doctorTotal = 0;
            foreach ($pasienList as $pi => $p) {
                $dr = $pi + 3;
                $biaya = $this->getBiayaPoli($p['NamaPoli'] ?? '');
                $jenis = match ($biaya) {
                    10000 => 'Tindakan Lab',
                    15000 => 'Tindakan Radiologi',
                    default => 'Konsul Dokter',
                };
                $doctorTotal += $biaya;

                $detailSheet->setCellValue([1, $dr], $pi + 1);
                $detailSheet->setCellValueExplicit([2, $dr], (string) ($p['RegistrationNo'] ?? ''), DataType::TYPE_STRING);
                $detailSheet->setCellValueExplicit([3, $dr], (string) ($p['NoRekamMedis'] ?? ''), DataType::TYPE_STRING);
                $detailSheet->setCellValueExplicit([4, $dr], (string) ($p['NamaPasien'] ?? ''), DataType::TYPE_STRING);
                $detailSheet->setCellValueExplicit([5, $dr], (string) ($p['NamaPoli'] ?? ''), DataType::TYPE_STRING);
                $detailSheet->setCellValueExplicit([6, $dr], $jenis, DataType::TYPE_STRING);
                $detailSheet->setCellValue([7, $dr], $biaya);
                $detailSheet->getStyle("G{$dr}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $detailSheet->setCellValueExplicit([8, $dr], (string) ($p['RegistrationTime'] ?? ''), DataType::TYPE_STRING);
                $detailSheet->getStyle("A{$dr}:H{$dr}")->applyFromArray($borderStyle);
            }

            // Baris total dokter
            $totalRow = count($pasienList) + 3;
            $detailSheet->mergeCells("A{$totalRow}:F{$totalRow}");
            $detailSheet->setCellValue("A{$totalRow}", 'TOTAL PENDAPATAN');
            $detailSheet->setCellValue([7, $totalRow], $doctorTotal);
            $detailSheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $detailSheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray($subtotalStyle);

            foreach (['A' => 5, 'B' => 18, 'C' => 15, 'D' => 34, 'E' => 24, 'F' => 20, 'G' => 16, 'H' => 10] as $col => $width) {
                $detailSheet->getColumnDimension($col)->setWidth($width);
            }
            $detailSheet->getRowDimension(1)->setRowHeight(22);
            $detailSheet->getRowDimension(2)->setRowHeight(30);
        }

        // Grand total baris ringkasan
        if ($docNo > 0) {
            $gtRow = $docNo + 3;
            $summary->mergeCells("A{$gtRow}:C{$gtRow}");
            $summary->setCellValue("A{$gtRow}", 'GRAND TOTAL');
            $summary->setCellValue([7, $gtRow], $grandTotal);
            $summary->getStyle("G{$gtRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $summary->getStyle("A{$gtRow}:G{$gtRow}")->applyFromArray($subtotalStyle);
            $summary->getStyle("A{$gtRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        if ($docNo > 0) {
            $summary->getStyle('A3:G'.($docNo + 2))->applyFromArray($borderStyle);
        }

        foreach (['A' => 5, 'B' => 32, 'C' => 12, 'D' => 18, 'E' => 14, 'F' => 18, 'G' => 20] as $col => $width) {
            $summary->getColumnDimension($col)->setWidth($width);
        }
        $summary->getRowDimension(2)->setRowHeight(36);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'xl_');
        $writer->save($tmpFile);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($tmpFile, "payslip-sore-{$tanggal}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
