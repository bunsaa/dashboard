<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    /**
     * Generate IT department recommendations from a review text.
     * Returns structured text with TITLE:, PENDEK:, MENENGAH:, PANJANG: markers,
     * or empty string if Gemini is not configured or an error occurs.
     */
    public function generateItRecommendation(string $reviewText, int $rating): string
    {
        if (! $this->isConfigured()) {
            return '';
        }

        $stars = str_repeat('⭐', max(1, $rating));
        $prompt = <<<PROMPT
Berikut adalah ulasan pasien untuk RSUD Tarakan DKI Jakarta:

Rating: {$rating}/5 {$stars}
Ulasan: "{$reviewText}"

Berdasarkan ulasan tersebut, buatkan rekomendasi tindakan untuk **Tim IT Rumah Sakit** saja (bukan manajemen umum atau tenaga medis).

Fokus pada: sistem informasi rumah sakit (SIMRS), jaringan/WiFi, antrian digital, aplikasi mobile, perangkat keras, keamanan data, dan infrastruktur teknologi.

Tulis dalam format berikut PERSIS seperti ini (jangan tambahkan teks lain di luar format):

TITLE:Judul singkat permasalahan IT yang ditemukan
PENDEK:Jangka Pendek — Segera Ditangani
- satu tindakan paling penting dan mendesak
MENENGAH:Jangka Menengah — 1 s.d. 3 Bulan
- satu tindakan perbaikan yang perlu direncanakan
PANJANG:Jangka Panjang — 3 s.d. 12 Bulan
- satu tindakan strategis jangka panjang

Setiap tindakan harus:
- Ditulis dalam bahasa Indonesia formal
- Berupa satu kalimat aktif yang spesifik (bukan saran umum)
- Relevan dengan IT rumah sakit
- Ringkas (maksimal 25 kata)
PROMPT;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-goog-api-key' => config('services.gemini.key'),
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => $prompt]],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 1024,
                        'temperature' => 0.4,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('GeminiService HTTP error: '.$response->status().' — '.$response->body());

                return '';
            }

            return $response->json('candidates.0.content.parts.0.text') ?? '';
        } catch (\Throwable $e) {
            Log::warning('GeminiService generateItRecommendation error: '.$e->getMessage());

            return '';
        }
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.gemini.key'));
    }
}
