<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\Instansi;
use App\Models\Monev\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitKerjaController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Monev/UnitKerja', [
            'instansi'  => Instansi::with('unitKerja')->orderBy('nama_instansi')->get(),
            'unitKerja' => UnitKerja::with('instansi')->orderBy('instansi_id')->orderBy('nama_unit_kerja')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'instansi_id'     => ['required', Rule::exists(Instansi::class, 'id')],
            'kode_unit_kerja' => 'nullable|string|max:50',
            'nama_unit_kerja' => 'required|string|max:255',
            'nama_atasan'     => 'nullable|string|max:255',
            'nip'             => 'nullable|string|max:50',
        ]);

        UnitKerja::create($request->only('instansi_id', 'kode_unit_kerja', 'nama_unit_kerja', 'nama_atasan', 'nip'));

        return redirect()->back()->with('success', 'Unit kerja berhasil ditambahkan.');
    }

    public function update(Request $request, UnitKerja $unitKerja)
    {
        $request->validate([
            'instansi_id'     => ['required', Rule::exists(Instansi::class, 'id')],
            'kode_unit_kerja' => 'nullable|string|max:50',
            'nama_unit_kerja' => 'required|string|max:255',
            'nama_atasan'     => 'nullable|string|max:255',
            'nip'             => 'nullable|string|max:50',
        ]);

        $unitKerja->update($request->only('instansi_id', 'kode_unit_kerja', 'nama_unit_kerja', 'nama_atasan', 'nip'));

        return redirect()->back()->with('success', 'Unit kerja berhasil diperbarui.');
    }

    public function destroy(UnitKerja $unitKerja)
    {
        $unitKerja->delete();

        return redirect()->back()->with('success', 'Unit kerja berhasil dihapus.');
    }
}
