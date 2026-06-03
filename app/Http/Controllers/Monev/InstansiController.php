<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Models\Monev\Instansi;
use Illuminate\Http\Request;

class InstansiController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['nama_instansi' => 'required|string|max:255']);
        Instansi::create(['nama_instansi' => $request->nama_instansi]);

        return redirect()->back()->with('success', 'Instansi berhasil ditambahkan.');
    }

    public function update(Request $request, Instansi $instansi)
    {
        $request->validate(['nama_instansi' => 'required|string|max:255']);
        $instansi->update(['nama_instansi' => $request->nama_instansi]);

        return redirect()->back()->with('success', 'Instansi berhasil diperbarui.');
    }

    public function destroy(Instansi $instansi)
    {
        $instansi->delete();

        return redirect()->back()->with('success', 'Instansi berhasil dihapus.');
    }
}
