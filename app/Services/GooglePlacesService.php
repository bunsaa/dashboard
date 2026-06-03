<?php

namespace App\Services;

use App\Services\Concerns\AnalyzesItKeywords;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    use AnalyzesItKeywords;

    public function fetchAndSyncReviews(): array
    {
        $apiKey = config('services.google.places_api_key');
        $placeId = config('services.google.place_id');

        if (! $apiKey || ! $placeId) {
            return ['synced' => 0, 'it_related' => 0, 'error' => 'Google Places API key atau Place ID belum dikonfigurasi.'];
        }

        try {
            $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => 'reviews',
                'language' => 'id',
                'reviews_sort' => 'newest',
                'key' => $apiKey,
            ]);

            if (! $response->successful()) {
                return ['synced' => 0, 'it_related' => 0, 'error' => 'Gagal menghubungi Google Places API.'];
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK') {
                return ['synced' => 0, 'it_related' => 0, 'error' => 'Google API error: '.($data['status'] ?? 'UNKNOWN')];
            }

            $reviews = $data['result']['reviews'] ?? [];
            $synced = 0;
            $itRelated = 0;

            foreach ($reviews as $review) {
                $text = $review['text'] ?? '';
                $rating = (int) ($review['rating'] ?? 0);

                $this->saveReview([
                    'review_id' => md5(($review['author_url'] ?? '') . ($review['time'] ?? '')),
                    'author_name' => $review['author_name'] ?? 'Anonim',
                    'author_url' => $review['author_url'] ?? null,
                    'profile_photo_url' => $review['profile_photo_url'] ?? null,
                    'rating' => $rating,
                    'text' => $text,
                    'language' => $review['language'] ?? 'id',
                    'review_time' => date('Y-m-d H:i:s', $review['time'] ?? time()),
                ]);

                $synced++;
                if ($this->analyzeItRelation($text)['is_it_related']) {
                    $itRelated++;
                }
            }

            return ['synced' => $synced, 'it_related' => $itRelated, 'error' => null];
        } catch (\Exception $e) {
            Log::error('GooglePlacesService error: '.$e->getMessage());

            return ['synced' => 0, 'it_related' => 0, 'error' => $e->getMessage()];
        }
    }

    public function seedDummyReviews(): void
    {
        $dummies = [
            ['review_id' => 'dummy_001', 'author_name' => 'Budi Santoso', 'rating' => 2, 'language' => 'id',
                'text' => 'Aplikasi pendaftaran online sering error dan tidak bisa dibuka. Sudah coba beberapa kali tapi selalu gagal. Tolong diperbaiki.',
                'review_time' => now()->subDays(5)],
            ['review_id' => 'dummy_002', 'author_name' => 'Siti Rahayu', 'rating' => 1, 'language' => 'id',
                'text' => 'WiFi di ruang tunggu sangat lambat dan sering putus. Susah untuk cek antrian online.',
                'review_time' => now()->subDays(10)],
            ['review_id' => 'dummy_003', 'author_name' => 'Ahmad Fauzi', 'rating' => 5, 'language' => 'id',
                'text' => 'Pelayanan sangat baik dan cepat. Dokternya ramah dan profesional.',
                'review_time' => now()->subDays(3)],
            ['review_id' => 'dummy_004', 'author_name' => 'Dewi Lestari', 'rating' => 3, 'language' => 'id',
                'text' => 'Sistem antrian komputer di loket sering hang, jadi antriannya kacau. Perlu perbaikan sistem IT.',
                'review_time' => now()->subDays(15)],
            ['review_id' => 'dummy_005', 'author_name' => 'Rudi Hartono', 'rating' => 4, 'language' => 'id',
                'text' => 'Fasilitas lumayan bagus. Tapi printer di bagian rekam medis sering macet.',
                'review_time' => now()->subDays(7)],
            ['review_id' => 'dummy_006', 'author_name' => 'Nur Hidayah', 'rating' => 2, 'language' => 'id',
                'text' => 'Website RSUD tidak bisa diakses untuk cek jadwal dokter. Sudah berhari-hari down.',
                'review_time' => now()->subDays(20)],
            ['review_id' => 'dummy_007', 'author_name' => 'Hendra Wijaya', 'rating' => 5, 'language' => 'id',
                'text' => 'Sekarang pendaftaran online sudah lebih mudah dan cepat. Terima kasih atas perbaikannya.',
                'review_time' => now()->subDays(2)],
            ['review_id' => 'dummy_008', 'author_name' => 'Fatimah Zahra', 'rating' => 1, 'language' => 'id',
                'text' => 'Mesin antrian mati dan tidak ada yang memperbaiki. Pasien bingung mau antri dimana. Tolong sistem IT-nya diperhatikan.',
                'review_time' => now()->subDays(1)],
        ];

        foreach ($dummies as $dummy) {
            $this->saveReview($dummy);
        }
    }
}
