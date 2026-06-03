<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BedController extends Controller
{
    private const BED_OCCUPIED = 'BedStatus-02';

    private const BED_AVAILABLE = 'BedStatus-01';

    public function index(): Response
    {
        try {
            $data = Cache::remember('bed.summary', 120, fn () => DB::connection('sqlsrv_report')->select(<<<'SQL'
                SELECT
                    su.ServiceUnitName                                                    AS NamaRuangan,
                    COUNT(*)                                                              AS TotalBed,
                    SUM(CASE WHEN b.SRBedStatus = ? THEN 1 ELSE 0 END)                   AS Terisi,
                    SUM(CASE WHEN b.SRBedStatus = ? THEN 1 ELSE 0 END)                   AS Kosong,
                    SUM(CASE WHEN b.SRBedStatus = ? AND pt.Sex = N'M' THEN 1 ELSE 0 END) AS LakiLaki,
                    SUM(CASE WHEN b.SRBedStatus = ? AND pt.Sex = N'F' THEN 1 ELSE 0 END) AS Perempuan
                FROM [dbo].[Bed] b WITH (NOLOCK)
                JOIN  [dbo].[ServiceRoom]  sr WITH (NOLOCK) ON sr.RoomID        = b.RoomID
                JOIN  [dbo].[ServiceUnit]  su WITH (NOLOCK) ON su.ServiceUnitID = sr.ServiceUnitID
                LEFT JOIN [dbo].[Registration] r  WITH (NOLOCK)
                       ON r.RegistrationNo  = b.RegistrationNo AND b.SRBedStatus = ?
                LEFT JOIN [dbo].[Patient]      pt WITH (NOLOCK)
                       ON pt.PatientID = r.PatientID
                WHERE b.IsActive = 1
                GROUP BY su.ServiceUnitName
                ORDER BY su.ServiceUnitName
            SQL, [
                self::BED_OCCUPIED,
                self::BED_AVAILABLE,
                self::BED_OCCUPIED,
                self::BED_OCCUPIED,
                self::BED_OCCUPIED,
            ]));

            $patients = Cache::remember('bed.patients', 120, fn () => DB::connection('sqlsrv_report')->select(<<<'SQL'
                SELECT
                    su.ServiceUnitName AS NamaRuangan,
                    b.BedID,
                    pt.MedicalNo AS NoRekamMedis,
                    LTRIM(RTRIM(COALESCE(
                        NULLIF(pt.FullName, ''),
                        RTRIM(COALESCE(pt.FirstName,'') + ' ' + COALESCE(pt.MiddleName,'') + ' ' + COALESCE(pt.LastName,''))
                    ))) AS NamaPasien,
                    pm.ParamedicName AS NamaDPJP,
                    CONVERT(VARCHAR(10), r.RegistrationDate, 103) AS TglMasuk,
                    COALESCE(g.GuarantorName, 'UMUM') AS Jaminan,
                    pt.Sex
                FROM [dbo].[Bed] b WITH (NOLOCK)
                JOIN  [dbo].[ServiceRoom]  sr WITH (NOLOCK) ON sr.RoomID        = b.RoomID
                JOIN  [dbo].[ServiceUnit]  su WITH (NOLOCK) ON su.ServiceUnitID = sr.ServiceUnitID
                JOIN  [dbo].[Registration] r  WITH (NOLOCK) ON r.RegistrationNo = b.RegistrationNo
                JOIN  [dbo].[Patient]      pt WITH (NOLOCK) ON pt.PatientID     = r.PatientID
                JOIN  [dbo].[Paramedic]    pm WITH (NOLOCK) ON pm.ParamedicID   = r.ParamedicID
                LEFT JOIN [dbo].[Guarantor] g WITH (NOLOCK) ON g.GuarantorID   = r.GuarantorID
                WHERE b.IsActive = 1
                  AND b.SRBedStatus = ?
                ORDER BY su.ServiceUnitName, b.BedID
            SQL, [self::BED_OCCUPIED]));

            $error = null;
        } catch (Throwable $e) {
            $data = [];
            $patients = [];
            $msg = $e->getMessage();
            $error = str_contains($msg, 'not supported') || str_contains($msg, 'could not find driver')
                ? 'Driver PHP SQL Server (pdo_sqlsrv) belum terpasang di server ini. Hubungi administrator.'
                : 'Tidak dapat terhubung ke database TARAKAN: '.$msg;
        }

        return Inertia::render('Bed/Index', [
            'data' => $data,
            'patients' => $patients,
            'updatedAt' => now()->format('d/m/Y H:i:s'),
            'error' => $error,
        ]);
    }
}
