<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        // Paksa ganti password jika masih menggunakan password default
        if ($user->nip && Hash::check('password', $user->password)) {
            return $request->wantsJson()
                ? new JsonResponse(['two_factor' => false], 200)
                : redirect('/force-change-password');
        }

        $team = $user?->currentTeam ?? $user?->personalTeam();

        if (! $team) {
            abort(403);
        }

        URL::defaults(['current_team' => $team->slug]);

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended("/{$team->slug}".Fortify::redirects('login'));
    }
}
