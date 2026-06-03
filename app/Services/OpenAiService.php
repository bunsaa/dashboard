<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiService
{
    // Groq — gratis, tanpa CC, 14.400 req/hari. Daftar: console.groq.com
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    private const MODEL = 'llama-3.3-70b-versatile';

    /**
     * Baca ulasan, tentukan apakah terkait IT, dan sekaligus generate rekomendasi
     * (jika terkait IT dan rating <= 3) — semua dalam SATU panggilan API.
     *
     * @return array{is_it_related: bool, recommendation: string|null, is_ai: bool}|null
     *         null = Groq belum dikonfigurasi atau teks kosong (gunakan fallback keyword)
     */
    public function classifyAndRecommend(string $reviewText, int $rating): ?array
    {
        if (! $this->isConfigured() || empty(trim($reviewText))) {
            return null;
        }

        $stars   = str_repeat('⭐', max(1, $rating));
        $needsRec = $rating <= 3;

        $recInstruction = $needsRec
            ? <<<'INSTR'
Jika YA, lanjutkan dengan rekomendasi tindakan untuk Tim IT dalam format:
IS_IT: YA
TITLE:Judul singkat permasalahan IT
PENDEK:Jangka Pendek — Segera Ditangani
- tindakan mendesak (2-3 kalimat: APA dilakukan, BAGAIMANA caranya, MENGAPA penting)
MENENGAH:Jangka Menengah — 1 s.d. 3 Bulan
- tindakan perbaikan sistematis (2-3 kalimat)
PANJANG:Jangka Panjang — 3 s.d. 12 Bulan
- tindakan strategis (2-3 kalimat)
INSTR
            : 'Jika YA, jawab hanya: IS_IT: YA';

        $prompt = <<<PROMPT
Baca ulasan pasien RSUD Tarakan berikut dengan teliti:

Rating: {$rating}/5 {$stars}
Ulasan: "{$reviewText}"

Apakah ulasan ini berisi keluhan atau masalah yang berkaitan dengan layanan IT rumah sakit (sistem komputer, aplikasi, WiFi, SIMRS, antrian digital, perangkat keras IT, dll)?

Jika TIDAK terkait IT, jawab hanya:
IS_IT: TIDAK

{$recInstruction}
PROMPT;

        $systemMessage = 'Kamu adalah analis IT rumah sakit. Baca ulasan pasien, tentukan apakah terkait masalah IT, '
            .'dan jika diminta tulis rekomendasi teknis spesifik untuk tim IT. '
            .'Bahasa Indonesia formal. IKUTI format yang diminta PERSIS, tanpa teks tambahan di luar format.';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.groq.key'),
                    'Content-Type'  => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model'    => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemMessage],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'max_tokens'  => $needsRec ? 1500 : 20,
                    'temperature' => 0.3,
                ]);

            if (! $response->successful()) {
                Log::warning('Groq classifyAndRecommend HTTP error: '.$response->status());

                return null;
            }

            $content = trim($response->json('choices.0.message.content') ?? '');

            if (empty($content)) {
                return null;
            }

            $isItRelated = (bool) preg_match('/^IS_IT:\s*YA/im', $content);

            $recommendation = null;
            if ($isItRelated && $needsRec) {
                $rec = preg_replace('/^IS_IT:[^\n]*\n?/im', '', $content);
                $rec = trim($rec);
                if (! empty($rec)) {
                    $recommendation = $rec;
                }
            }

            return [
                'is_it_related'  => $isItRelated,
                'recommendation' => $recommendation,
                'is_ai'          => true,
            ];

        } catch (\Throwable $e) {
            Log::warning('Groq classifyAndRecommend error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Generate IT department recommendations from a review text.
     * Returns structured text with TITLE:, PENDEK:, MENENGAH:, PANJANG: markers,
     * or empty string if not configured or an error occurs.
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
- tindakan mendesak yang harus dilakukan tim IT secepatnya
MENENGAH:Jangka Menengah — 1 s.d. 3 Bulan
- tindakan perbaikan sistematis yang perlu direncanakan
PANJANG:Jangka Panjang — 3 s.d. 12 Bulan
- tindakan strategis untuk mencegah masalah serupa di masa depan

Panduan penulisan setiap tindakan:
- Bahasa Indonesia formal dan profesional
- WAJIB 2-3 kalimat lengkap (bukan 1 frasa singkat): jelaskan APA yang dilakukan, BAGAIMANA caranya, dan MENGAPA penting
- Spesifik dan langsung dapat dieksekusi oleh tim IT (bukan saran umum seperti "perbarui sistem")
- Relevan dengan permasalahan IT yang disebutkan dalam ulasan

Contoh tindakan yang BENAR (cukup detail):
- Tim IT segera periksa log error pada server aplikasi pendaftaran online untuk mengidentifikasi penyebab gangguan secara spesifik. Lakukan restart service yang bermasalah dan verifikasi sistem kembali dapat diakses pasien sebelum jam layanan dimulai. Catat kronologi insiden untuk keperluan analisis perbaikan lebih lanjut.

Contoh tindakan yang SALAH (terlalu singkat, jangan seperti ini):
- Perbarui sistem antrian online
PROMPT;

        $systemMessage = 'Kamu adalah konsultan IT rumah sakit yang berpengalaman. '
            .'Tugasmu menulis rekomendasi tindakan teknis yang spesifik, actionable, dan cukup detail — '
            .'setiap tindakan terdiri dari 2-3 kalimat penuh yang menjelaskan APA yang dilakukan, BAGAIMANA caranya, dan MENGAPA penting. '
            .'JANGAN tulis hanya 1 frasa singkat. IKUTI format yang diminta PERSIS, tanpa teks tambahan di luar format.';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.groq.key'),
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemMessage],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 1500,
                    'temperature' => 0.4,
                ]);

            if (! $response->successful()) {
                Log::warning('GroqService HTTP error: '.$response->status().' — '.$response->body());

                return '';
            }

            return $response->json('choices.0.message.content') ?? '';
        } catch (\Throwable $e) {
            Log::warning('GroqService generateItRecommendation error: '.$e->getMessage());

            return '';
        }
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.groq.key'));
    }
}
