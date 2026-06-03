<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    const DEFAULT_PASSWORD = 'password';

    /**
     * Reset password ke default "password" berdasarkan NIP.
     */
    public function resetToDefault(Request $request)
    {
        $request->validate([
            'nip' => 'required|string',
        ]);

        $user = User::where('nip', $request->nip)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'nip' => ['NIP tidak ditemukan dalam sistem.'],
            ]);
        }

        $user->password = Hash::make(self::DEFAULT_PASSWORD);
        $user->save();

        return back()->with('status', 'reset_success');
    }

    /**
     * Tampilkan halaman paksa ganti password.
     */
    public function showForceChange()
    {
        return Inertia::render('auth/ForceChangePassword');
    }

    /**
     * Proses paksa ganti password.
     */
    public function processForceChange(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($request->password === self::DEFAULT_PASSWORD) {
            throw ValidationException::withMessages([
                'password' => ['Password baru tidak boleh sama dengan password default.'],
            ]);
        }

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();

        $team = $user->currentTeam;

        return $team
            ? redirect("/{$team->slug}/dashboard")
            : redirect('/');
    }
}
