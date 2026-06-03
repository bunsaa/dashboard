<?php

namespace App\Http\Controllers\ManajemenPegawai;

use App\Exports\PegawaiTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PegawaiImport;
use App\Models\Units;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PegawaiController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $users = User::with('unit')->orderBy('name', 'asc')->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'nip' => $u->nip,
            'role' => $u->role ?? 'staf',
            'status_pegawai' => $u->status_pegawai,
            'status_kerja' => $u->status_kerja,
            'kode_unit' => $u->kode_unit,
            'unit' => $u->unit ? ['kode_unit' => $u->unit->kode_unit, 'nama_unit' => $u->unit->nama_unit] : null,
        ]);

        $units = Units::orderBy('kode_unit', 'asc')->get();

        return Inertia::render('ManajemenPegawai/Index', [
            'users' => $users,
            'units' => $units,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:30|unique:users,nip',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin_mutu,kepala_unit,staf',
            'status_pegawai' => 'nullable|in:PNS,CPNS,PPPK,PPPK Paruh Waktu,Pegawai Blud (Tetap Non ASN),PJLP,Mitra,Pegawai Lainnya Non ASN',
            'status_kerja' => 'nullable|in:Aktif,Resign,Pensiun,Mutasi',
            'kode_unit' => 'nullable|exists:units,kode_unit',
        ], [
            'name.required' => 'Nama wajib diisi',
            'nip.unique' => 'NIP sudah digunakan',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 6 karakter',
            'role.required' => 'Peran wajib dipilih',
        ]);

        $nip = $request->nip ?: null;
        $email = $nip ? $nip.'@rsud.local' : uniqid().'@rsud.local';

        User::create([
            'name' => $request->name,
            'email' => $email,
            'nip' => $nip,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status_pegawai' => $request->status_pegawai,
            'status_kerja' => $request->status_kerja,
            'kode_unit' => $request->kode_unit,
        ]);

        return redirect()->back()->with('success', 'Pegawai berhasil ditambahkan!');
    }

    public function update(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $pegawai = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:30|unique:users,nip,'.$id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:admin_mutu,kepala_unit,staf',
            'status_pegawai' => 'nullable|in:PNS,CPNS,PPPK,PPPK Paruh Waktu,Pegawai Blud (Tetap Non ASN),PJLP,Mitra,Pegawai Lainnya Non ASN',
            'status_kerja' => 'nullable|in:Aktif,Resign,Pensiun,Mutasi',
            'kode_unit' => 'nullable|exists:units,kode_unit',
        ], [
            'name.required' => 'Nama wajib diisi',
            'nip.unique' => 'NIP sudah digunakan',
            'password.min' => 'Password minimal 6 karakter',
            'role.required' => 'Peran wajib dipilih',
        ]);

        $updateData = [
            'name' => $request->name,
            'nip' => $request->nip ?: null,
            'role' => $request->role,
            'status_pegawai' => $request->status_pegawai,
            'status_kerja' => $request->status_kerja,
            'kode_unit' => $request->kode_unit,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $pegawai->update($updateData);

        return redirect()->back()->with('success', 'Pegawai berhasil diupdate!');
    }

    public function template(): BinaryFileResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        return Excel::download(new PegawaiTemplateExport(), 'template-pegawai.xlsx');
    }

    public function import(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ], [
            'file.required' => 'File Excel wajib dipilih',
            'file.mimes' => 'File harus berformat Excel (.xlsx atau .xls)',
            'file.max' => 'Ukuran file maksimal 5MB',
        ]);

        $import = new PegawaiImport();
        Excel::import($import, $request->file('file'));

        return redirect()->back()
            ->with('import_success', $import->imported)
            ->with('import_failures', $import->failures);
    }

    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        if ($user->id === $id) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus akun sendiri!');
        }

        User::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Pegawai berhasil dihapus!');
    }
}
