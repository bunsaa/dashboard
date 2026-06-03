<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CaptchaController extends Controller
{
    /**
     * Generate a captcha image (penjumlahan, pengurangan, perkalian; hasil 0–9).
     */
    public function generate(Request $request)
    {
        // Pilih tipe captcha secara acak
        $type = rand(0, 2); // 0=tambah, 1=kurang, 2=kali

        switch ($type) {
            case 0: // Penjumlahan: a + b = ? (hasil 0–9)
                $a = rand(0, 9);
                $b = rand(0, 9 - $a);
                $text = "$a + $b = ?";
                $answer = (string) ($a + $b);
                break;

            case 1: // Pengurangan: a - b = ? (hasil 0–9)
                $a = rand(0, 9);
                $b = rand(0, $a);
                $text = "$a - $b = ?";
                $answer = (string) ($a - $b);
                break;

            default: // Perkalian: a × b = ? (hasil 0–9)
                $a = rand(1, 3);
                $b = rand(0, (int) floor(9 / $a));
                $text = "$a × $b = ?";
                $answer = (string) ($a * $b);
                break;
        }

        $request->session()->put('captcha_answer', $answer);

        // Generate gambar captcha
        $width = 180;
        $height = 50;
        $image = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocate($image, 248, 249, 250);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);

        $borderColor = imagecolorallocate($image, 210, 215, 220);
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);

        // Noise dots
        for ($i = 0; $i < 200; $i++) {
            $dotColor = imagecolorallocate($image, rand(180, 230), rand(180, 230), rand(180, 230));
            imagesetpixel($image, rand(0, $width), rand(0, $height), $dotColor);
        }

        // Random lines
        for ($i = 0; $i < 4; $i++) {
            $lineColor = imagecolorallocate($image, rand(170, 210), rand(170, 210), rand(170, 210));
            imageline($image, rand(0, 30), rand(0, $height), rand($width - 30, $width), rand(0, $height), $lineColor);
        }

        // Render teks dengan TTF atau built-in font
        $fontPath = 'C:\\Windows\\Fonts\\arial.ttf';
        $fontExists = function_exists('imagettftext') && file_exists($fontPath);

        if ($fontExists) {
            $fontSize = 20;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $startX = ($width - $textWidth) / 2;
            $baseY = ($height + $fontSize) / 2;
            $currentX = $startX;

            for ($i = 0; $i < mb_strlen($text); $i++) {
                $char = mb_substr($text, $i, 1);
                $angle = rand(-8, 8);
                $color = imagecolorallocate($image, rand(20, 80), rand(20, 80), rand(20, 80));
                imagettftext($image, $fontSize + rand(-1, 1), $angle, (int) $currentX, (int) $baseY + rand(-2, 2), $color, $fontPath, $char);
                $charBbox = imagettfbbox($fontSize, 0, $fontPath, $char);
                $currentX += abs($charBbox[2] - $charBbox[0]) + rand(1, 3);
            }
        } else {
            $fontSize = 5;
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $x = ($width - $textWidth) / 2;
            $y = ($height - imagefontheight($fontSize)) / 2;
            $textColor = imagecolorallocate($image, rand(0, 80), rand(0, 80), rand(0, 80));
            imagestring($image, $fontSize, (int) $x, (int) $y, $text, $textColor);
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return response()->json([
            'image' => 'data:image/png;base64,'.base64_encode($imageData),
        ]);
    }
}
