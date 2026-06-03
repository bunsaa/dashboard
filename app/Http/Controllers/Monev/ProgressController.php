<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\Instansi;
use App\Models\Monev\Kontrak;
use App\Models\Monev\ProgressKontrak;
use App\Models\Monev\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $isFullAdmin = $user->role === 'admin_mutu';
        $isKepalaUnit = $user->role === 'kepala_unit';
        $isAdmin = $isFullAdmin || $isKepalaUnit;
        $canDelete = $isFullAdmin || $isKepalaUnit;

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

        $query = Kontrak::with([
            'uraianKegiatan.aktivitas.unitKerja.instansi',
            'progress' => fn ($q) => $q->whereNull('parent_id')->orderBy('tanggal_mulai'),
        ])->whereNotNull('no_kontrak')->orderBy('no_kontrak');

        if ($unitKerjaId) {
            $query->whereHas('uraianKegiatan.aktivitas', fn ($q) => $q->where('unit_kerja_id', $unitKerjaId));
        } else {
            // Unit kerja wajib dipilih sebelum kontrak ditampilkan
            $query->whereRaw('0=1');
        }

        $kontrak = $query->get()->map(fn ($k) => [
            'id' => $k->id,
            'no_kontrak' => $k->no_kontrak,
            'uraian_pekerjaan' => $k->uraian_pekerjaan,
            'uraian_kegiatan' => $k->uraianKegiatan?->uraian_kegiatan,
            'jenis_kegiatan' => $k->uraianKegiatan?->aktivitas?->jenis_kegiatan,
            'tanggal_kontrak' => $k->tanggal_kontrak?->format('Y-m-d'),
            'tanggal_mulai' => $k->tanggal_mulai?->format('Y-m-d'),
            'tanggal_akhir' => $k->tanggal_akhir?->format('Y-m-d'),
            'dokumen_url' => $k->dokumen_kontrak_path
                ? Storage::disk('public')->url($k->dokumen_kontrak_path)
                : null,
            'dokumen_name' => $k->dokumen_kontrak_path
                ? basename($k->dokumen_kontrak_path)
                : null,
            'progress' => $k->progress->map(fn ($p) => [
                'id' => $p->id,
                'uraian_progress' => $p->uraian_progress,
                'sumber' => $p->sumber ?? 'vendor',
                'tanggal_mulai' => $p->getRawOriginal('tanggal_mulai'),
                'tanggal_akhir' => $p->getRawOriginal('tanggal_akhir'),
                'file_url' => $p->file_path ? Storage::disk('public')->url($p->file_path) : null,
                'file_name' => $p->file_path ? basename($p->file_path) : null,
                'created_by' => $p->created_by,
                'last_update_by' => $p->last_update_by,
                'durasi_hari' => $p->durasi_hari,
                'status' => $p->status ?? 'draft',
                'reviewed_by' => $p->reviewed_by,
                'kabag_comment' => $p->kabag_comment,
                'comment_resolved' => (bool) ($p->comment_resolved ?? false),
            ])->values()->toArray(),
        ]);

        $instansiList = $isFullAdmin ? Instansi::orderBy('nama_instansi')->get(['id', 'nama_instansi']) : collect();
        $unitKerjaList = ($isFullAdmin && $instansiId)
            ? UnitKerja::where('instansi_id', $instansiId)->orderBy('nama_unit_kerja')->get(['id', 'nama_unit_kerja'])
            : collect();

        return Inertia::render('Monev/Progress', [
            'kontrak' => $kontrak,
            'instansiList' => $instansiList,
            'unitKerjaList' => $unitKerjaList,
            'selectedInstansi' => $instansiId ? (int) $instansiId : null,
            'selectedUnit' => $unitKerjaId ? (int) $unitKerjaId : null,
            'selectedUnitName' => $unitKerjaName ?? null,
            'canApprove' => $isAdmin,
            'canStore' => ! $isKepalaUnit,
            'canDelete' => $canDelete,
            'userRole' => $user->role ?? 'staf',
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->role === 'kepala_unit') {
            abort(403, 'Kepala unit tidak dapat menambah progress.');
        }

        $data = $request->validate([
            'kontrak_id' => ['required', Rule::exists(Kontrak::class, 'id')],
            'uraian_progress' => 'required|string|max:2000',
            'sumber' => 'required|in:internal,vendor',
            'durasi_hari' => 'nullable|integer|min:1|max:9999',
            'tanggal_mulai' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_mulai',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx|max:10240',
        ]);

        // Staf hanya boleh isi progress di bulan berjalan
        if ($user->role === 'staf') {
            $currentMonth = now()->format('Y-m');
            $submittedMonth = substr($data['tanggal_mulai'], 0, 7);
            if ($submittedMonth !== $currentMonth) {
                return back()->withErrors(['tanggal_mulai' => 'Staf hanya dapat menambah progress di bulan berjalan ('.now()->translatedFormat('F Y').').'])->withInput();
            }
        }

        $kontrak = Kontrak::find($data['kontrak_id']);
        if ($kontrak?->tanggal_mulai) {
            $minDate = $kontrak->tanggal_mulai->format('Y-m-d');
            if ($data['tanggal_mulai'] < $minDate) {
                return back()->withErrors(['tanggal_mulai' => "Tanggal awal tidak boleh sebelum tanggal mulai kontrak ({$minDate})."])->withInput();
            }
            if ($data['tanggal_akhir'] < $minDate) {
                return back()->withErrors(['tanggal_akhir' => "Tanggal selesai tidak boleh sebelum tanggal mulai kontrak ({$minDate})."])->withInput();
            }
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('progress', 'public');
        }

        ProgressKontrak::create([
            'kontrak_id' => $data['kontrak_id'],
            'uraian_progress' => $data['uraian_progress'],
            'sumber' => $data['sumber'],
            'durasi_hari' => $data['durasi_hari'] ?? null,
            'created_by' => auth()->user()->name,
            'tipe' => 'mingguan',
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_akhir' => $data['tanggal_akhir'],
            'persen_rencana' => 0,
            'persen_realisasi' => 0,
            'file_path' => $filePath,
        ]);

        return redirect()->back();
    }

    public function approve(ProgressKontrak $progress)
    {
        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin_mutu', 'kepala_unit']);

        if (! $isAdmin) {
            abort(403);
        }

        if ($progress->tanggal_akhir && $progress->tanggal_akhir > now()) {
            return redirect()->back()->with('error', 'Progress belum bisa disetujui — pekerjaan belum selesai.');
        }

        $progress->update([
            'status' => 'approved',
            'reviewed_by' => $user->name,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Progress disetujui.');
    }

    public function reject(Request $request, ProgressKontrak $progress)
    {
        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin_mutu', 'kepala_unit']);

        if (! $isAdmin) {
            abort(403);
        }

        if ($progress->tanggal_akhir && $progress->tanggal_akhir > now()) {
            return redirect()->back()->with('error', 'Progress belum bisa ditolak — pekerjaan belum selesai.');
        }

        $data = $request->validate(['kabag_comment' => 'required|string|max:1000']);

        $progress->update([
            'status' => 'rejected',
            'kabag_comment' => $data['kabag_comment'],
            'comment_resolved' => false,
            'reviewed_by' => $user->name,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Progress ditolak dengan komentar.');
    }

    public function bulkApprove(Request $request)
    {
        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin_mutu', 'kepala_unit']);

        if (! $isAdmin) {
            abort(403);
        }

        $data = $request->validate(['kontrak_id' => 'required|exists:kontrak,id']);
        $count = ProgressKontrak::where('kontrak_id', $data['kontrak_id'])
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->whereDate('tanggal_akhir', '<=', now()->toDateString())
            ->update([
                'status' => 'approved',
                'reviewed_by' => $user->name,
                'reviewed_at' => now(),
            ]);

        return redirect()->back()->with('success', "{$count} progress berhasil disetujui.");
    }

    public function bulkReject(Request $request)
    {
        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin_mutu', 'kepala_unit']);

        if (! $isAdmin) {
            abort(403);
        }

        $data = $request->validate(['kontrak_id' => 'required|exists:kontrak,id', 'kabag_comment' => 'required|string|max:1000']);
        $count = ProgressKontrak::where('kontrak_id', $data['kontrak_id'])
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->whereDate('tanggal_akhir', '<=', now()->toDateString())
            ->update([
                'status' => 'rejected',
                'kabag_comment' => $data['kabag_comment'],
                'comment_resolved' => false,
                'reviewed_by' => $user->name,
                'reviewed_at' => now(),
            ]);

        return redirect()->back()->with('success', "{$count} progress berhasil ditolak.");
    }

    public function resolveComment(ProgressKontrak $progress)
    {
        $progress->update([
            'comment_resolved' => true,
            'status' => 'draft',
            'last_update_by' => auth()->user()->name,
        ]);

        return redirect()->back()->with('success', 'Komentar ditandai sudah diperbaiki.');
    }

    public function update(Request $request, ProgressKontrak $progress)
    {
        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin_mutu', 'kepala_unit']);

        if ($user->role === 'kepala_unit') {
            abort(403, 'Kepala unit tidak dapat mengubah progress.');
        }

        if (! $isAdmin && $progress->status !== 'draft') {
            return redirect()->back()->with('error', 'Progress yang sudah di-approve/reject tidak dapat diubah.');
        }

        $data = $request->validate([
            'uraian_progress' => 'required|string|max:2000',
            'sumber' => 'required|in:internal,vendor',
            'durasi_hari' => 'nullable|integer|min:1|max:9999',
            'tanggal_mulai' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_mulai',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx|max:10240',
            'remove_file' => 'nullable|boolean',
        ]);

        $kontrak = $progress->kontrak;
        if ($kontrak?->tanggal_mulai) {
            $minDate = $kontrak->tanggal_mulai->format('Y-m-d');
            if ($data['tanggal_mulai'] < $minDate) {
                return back()->withErrors(['tanggal_mulai' => "Tanggal awal tidak boleh sebelum tanggal mulai kontrak ({$minDate})."])->withInput();
            }
        }

        $filePath = $progress->file_path;

        if ($request->boolean('remove_file')) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = null;
        }

        if ($request->hasFile('file')) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $request->file('file')->store('progress', 'public');
        }

        $progress->update([
            'uraian_progress' => $data['uraian_progress'],
            'sumber' => $data['sumber'],
            'durasi_hari' => $data['durasi_hari'] ?? $progress->durasi_hari,
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_akhir' => $data['tanggal_akhir'],
            'file_path' => $filePath,
            'last_update_by' => $user->name,
        ]);

        return redirect()->back();
    }

    public function destroy(ProgressKontrak $progress)
    {
        $user = auth()->user();
        if (! in_array($user->role, ['admin_mutu', 'kepala_unit'])) {
            abort(403);
        }

        $progress->update([
            'last_update_by' => $user->name,
            'deleted_by' => $user->name,
        ]);

        $progress->delete();

        return redirect()->back();
    }
}
