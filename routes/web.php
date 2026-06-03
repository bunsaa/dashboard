<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\DownloadReportController;
use App\Http\Controllers\ManajemenPegawai\PegawaiController;
use App\Http\Controllers\Monev\AktivitasController;
use App\Http\Controllers\Monev\AnggPengadaanController;
use App\Http\Controllers\Monev\InstansiController;
use App\Http\Controllers\Monev\KontrakController;
use App\Http\Controllers\Monev\MonevDashboardController;
use App\Http\Controllers\Monev\ProgressController;
use App\Http\Controllers\Monev\UnitKerjaController;
use App\Http\Controllers\Monev\UraianKegiatanController;
use App\Http\Controllers\Monev\VendorController;
use App\Http\Controllers\Monitoring\MonitoringController;
use App\Http\Controllers\PenilaianPerilaku\PenilaianPerilakuController;
use App\Http\Controllers\Renkin\GoogleReviewController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

// Halaman awal redirect ke login
Route::redirect('/', '/login')->name('home');

// Captcha
Route::get('/captcha', [CaptchaController::class, 'generate'])->name('captcha');

// Reset password via NIP (tanpa email)
Route::post('/reset-password-default', [PasswordResetController::class, 'resetToDefault'])->name('password.reset-default');

// Paksa ganti password (hanya perlu login, bukan verified)
Route::middleware(['auth'])->group(function () {
    Route::get('/force-change-password', [PasswordResetController::class, 'showForceChange'])->name('force-change-password');
    Route::post('/force-change-password', [PasswordResetController::class, 'processForceChange'])->name('force-change-password.process');
});

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        // BED RSUD Tarakan (data real-time dari SQL Server)
        Route::get('/bed', [BedController::class, 'index'])->name('bed');

        // Download Report (data pelayanan RS dari SQL Server)
        Route::get('/download-report', function (string $current_team) {
            return redirect("/{$current_team}/download-report/rawat-jalan");
        })->name('download-report');
        Route::prefix('download-report')->name('download-report.')->group(function () {
            Route::get('/rawat-jalan', [DownloadReportController::class, 'rawatJalan'])->name('rawat-jalan');
            Route::get('/rawat-jalan/excel', [DownloadReportController::class, 'exportRawatJalan'])->name('rawat-jalan.excel');
            Route::get('/rawat-inap', [DownloadReportController::class, 'rawatInap'])->name('rawat-inap');
            Route::get('/rawat-inap/excel', [DownloadReportController::class, 'exportRawatInap'])->name('rawat-inap.excel');
            Route::get('/billing-non-bpjs', [DownloadReportController::class, 'billingNonBpjs'])->name('billing-non-bpjs');
            Route::get('/billing-non-bpjs/excel', [DownloadReportController::class, 'exportBillingNonBpjs'])->name('billing-non-bpjs.excel');
            Route::get('/kunjungan-dokter', [DownloadReportController::class, 'kunjunganDokter'])->name('kunjungan-dokter');
            Route::get('/kunjungan-dokter/excel', [DownloadReportController::class, 'exportKunjunganDokter'])->name('kunjungan-dokter.excel');
            Route::get('/kunjungan-pasien', [DownloadReportController::class, 'kunjunganPasien'])->name('kunjungan-pasien');
            Route::get('/kunjungan-pasien/excel', [DownloadReportController::class, 'exportKunjunganPasien'])->name('kunjungan-pasien.excel');
            Route::get('/kunjungan-pasien/payslip', [DownloadReportController::class, 'exportPayslipSore'])->name('kunjungan-pasien.payslip');
        });

        // Monitoring
        Route::prefix('monitoring')->name('monitoring.')->group(function () {
            Route::get('/beda-kelas-peserta', [MonitoringController::class, 'bedaKelas'])->name('beda-kelas-peserta');
            Route::get('/beda-kelas-peserta/{tanggal}', [MonitoringController::class, 'bedaKelasDetail'])->name('beda-kelas-peserta.detail');
            Route::get('/klaim-bpjs', [MonitoringController::class, 'klaimBpjs'])->name('klaim-bpjs');
        });

        // Penilaian Perilaku
        Route::prefix('penilaian-perilaku')->name('penilaian-perilaku.')->group(function () {
            Route::get('/', [PenilaianPerilakuController::class, 'home'])->name('home');
            Route::get('/pegawai', [PenilaianPerilakuController::class, 'index'])->name('pegawai');
            Route::get('/pegawai/export', [PenilaianPerilakuController::class, 'export'])->name('pegawai.export');
            Route::post('/pegawai', [PenilaianPerilakuController::class, 'store'])->name('pegawai.store');
            Route::post('/pegawai/show', [PenilaianPerilakuController::class, 'show'])->name('pegawai.show');
            Route::put('/pegawai/{id}', [PenilaianPerilakuController::class, 'update'])->name('pegawai.update');
            Route::get('/saya', [PenilaianPerilakuController::class, 'indexSaya'])->name('saya');
            Route::get('/pengaturan', [PenilaianPerilakuController::class, 'pengaturan'])->name('pengaturan');
            Route::post('/pengaturan/toggle/{id}', [PenilaianPerilakuController::class, 'togglePenilaian'])->name('pengaturan.toggle');
            Route::post('/pengaturan/toggle-all', [PenilaianPerilakuController::class, 'toggleAllPenilaian'])->name('pengaturan.toggle-all');
        });

        // Manajemen Pegawai
        Route::prefix('manajemen-pegawai')->name('manajemen-pegawai.')->group(function () {
            Route::get('/', [PegawaiController::class, 'index'])->name('index');
            Route::post('/', [PegawaiController::class, 'store'])->name('store');
            Route::get('/template', [PegawaiController::class, 'template'])->name('template');
            Route::post('/import', [PegawaiController::class, 'import'])->name('import');
            Route::put('/{id}', [PegawaiController::class, 'update'])->name('update');
            Route::delete('/{id}', [PegawaiController::class, 'destroy'])->name('destroy');
        });

        // Renkin - Monitoring Google Reviews IT
        Route::prefix('renkin')->name('renkin.')->group(function () {
            Route::get('google-reviews', [GoogleReviewController::class, 'index'])->name('google-reviews');
            Route::post('google-reviews/sync', [GoogleReviewController::class, 'sync'])->name('google-reviews.sync');
            Route::get('google-reviews/quota', [GoogleReviewController::class, 'quota'])->name('google-reviews.quota');
            Route::post('google-reviews/seed-dummy', [GoogleReviewController::class, 'seedDummy'])->name('google-reviews.seed-dummy');
            Route::post('google-reviews/regenerate-ai', [GoogleReviewController::class, 'regenerateAiRecommendations'])->name('google-reviews.regenerate-ai');
        });

        // Monitoring dan Evaluasi
        Route::prefix('monev')->name('monev.')->group(function () {
            Route::get('/dashboard', [MonevDashboardController::class, 'index'])->name('dashboard');
            Route::post('/anggaran', [AnggPengadaanController::class, 'store'])->name('anggaran.store');

            Route::get('/vendor', [VendorController::class, 'index'])->name('vendor');
            Route::get('/vendor/export', [VendorController::class, 'export'])->name('vendor.export');

            Route::get('/unit-kerja', [UnitKerjaController::class, 'index'])->name('unit-kerja');
            Route::post('/unit-kerja', [UnitKerjaController::class, 'store'])->name('unit-kerja.store');
            Route::put('/unit-kerja/{unitKerja}', [UnitKerjaController::class, 'update'])->name('unit-kerja.update');
            Route::delete('/unit-kerja/{unitKerja}', [UnitKerjaController::class, 'destroy'])->name('unit-kerja.destroy');

            Route::post('/instansi', [InstansiController::class, 'store'])->name('instansi.store');
            Route::put('/instansi/{instansi}', [InstansiController::class, 'update'])->name('instansi.update');
            Route::delete('/instansi/{instansi}', [InstansiController::class, 'destroy'])->name('instansi.destroy');

            Route::get('/aktivitas', [AktivitasController::class, 'index'])->name('aktivitas');
            Route::post('/aktivitas', [AktivitasController::class, 'store'])->name('aktivitas.store');
            Route::put('/aktivitas/{aktivitas}', [AktivitasController::class, 'update'])->name('aktivitas.update');
            Route::delete('/aktivitas/{aktivitas}', [AktivitasController::class, 'destroy'])->name('aktivitas.destroy');

            Route::post('/aktivitas/{aktivitas}/uraian', [UraianKegiatanController::class, 'store'])->name('uraian.store');
            Route::post('/aktivitas/{aktivitas}/uraian/import', [UraianKegiatanController::class, 'import'])->name('uraian.import');
            Route::put('/uraian/{uraianKegiatan}', [UraianKegiatanController::class, 'update'])->name('uraian.update');
            Route::delete('/uraian/{uraianKegiatan}', [UraianKegiatanController::class, 'destroy'])->name('uraian.destroy');

            Route::get('/kontrak/export', [KontrakController::class, 'export'])->name('kontrak.export');
            Route::get('/kontrak', [KontrakController::class, 'index'])->name('kontrak');
            Route::post('/kontrak', [KontrakController::class, 'store'])->name('kontrak.store');
            Route::put('/kontrak/{kontrak}', [KontrakController::class, 'update'])->name('kontrak.update');
            Route::delete('/kontrak/{kontrak}', [KontrakController::class, 'destroy'])->name('kontrak.destroy');

            Route::post('/progress/bulk-approve', [ProgressController::class, 'bulkApprove'])->name('progress.bulk-approve');
            Route::post('/progress/bulk-reject', [ProgressController::class, 'bulkReject'])->name('progress.bulk-reject');
            Route::post('/progress/{progress}/approve', [ProgressController::class, 'approve'])->name('progress.approve');
            Route::post('/progress/{progress}/reject', [ProgressController::class, 'reject'])->name('progress.reject');
            Route::post('/progress/{progress}/resolve', [ProgressController::class, 'resolveComment'])->name('progress.resolve');
            Route::get('/progress', [ProgressController::class, 'index'])->name('progress');
            Route::post('/progress', [ProgressController::class, 'store'])->name('progress.store');
            Route::put('/progress/{progress}', [ProgressController::class, 'update'])->name('progress.update');
            Route::delete('/progress/{progress}', [ProgressController::class, 'destroy'])->name('progress.destroy');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
