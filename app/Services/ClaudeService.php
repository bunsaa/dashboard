<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private const MODEL = 'claude-haiku-4-5';

    /**
     * Generate IT department recommendations from a review text.
     * Returns structured text with TITLE:, PENDEK:, MENENGAH:, PANJANG: markers,
     * or empty string if Claude is not configured or an error occurs.
     *
     * Menggunakan Laravel HTTP client langsung ke Anthropic API —
     * tidak memerlukan package tambahan.
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
                    'x-api-key' => config('services.anthropic.key'),
                    'anthropic-version' => self::API_VERSION,
                    'content-type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => self::MODEL,
                    'max_tokens' => 1024,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('ClaudeService HTTP error: '.$response->status().' — '.$response->body());

                return '';
            }

            return $response->json('content.0.text') ?? '';
        } catch (\Throwable $e) {
            Log::warning('ClaudeService generateItRecommendation error: '.$e->getMessage());

            return '';
        }
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.anthropic.key'));
    }
}
