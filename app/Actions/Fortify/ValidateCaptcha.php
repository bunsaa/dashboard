<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ValidateCaptcha
{
    public function handle(Request $request, $next): mixed
    {
        $captchaAnswer = $request->session()->get('captcha_answer');
        $userAnswer = trim($request->input('captcha', ''));

        if (! $captchaAnswer || $userAnswer !== (string) $captchaAnswer) {
            throw ValidationException::withMessages([
                'captcha' => ['Kode captcha tidak sesuai.'],
            ]);
        }

        $request->session()->forget('captcha_answer');

        return $next($request);
    }
}
