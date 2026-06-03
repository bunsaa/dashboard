<?php

namespace App\Services;

use App\Services\Concerns\AnalyzesItKeywords;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiService
{
    use AnalyzesItKeywords;

    /**
     * Ambil review dari Google Maps via SerpAPI lalu simpan ke database lokal.
     * SerpAPI free tier: 100 request/bulan (tanpa kartu kredit).
     *
     * @param  int  $maxPages  Batas halaman review yang diambil (1 halaman ≈ 10 review, 1 request)
     */
    public function fetchAndSyncReviews(int $maxPages = 3): array
    {
        $apiKey = config('services.serpapi.key');
        $placeId = config('services.google.place_id');
        $dataId = config('services.google.data_id');

        if (! $apiKey) {
            return [
                'synced' => 0,
                'it_related' => 0,
                'error' => 'SerpAPI key belum dikonfigurasi. Tambahkan SERPAPI_KEY di file .env',
            ];
        }

        if (! $placeId && ! $dataId) {
            return [
                'synced' => 0,
                'it_related' => 0,
                'error' => 'Google Place ID belum dikonfigurasi. Tambahkan GOOGLE_PLACE_ID di file .env',
            ];
        }

        try {
            $allReviews = [];
            $nextPageToken = null;
            $page = 0;
            $apiCallsUsed = 0;

            // Ambil review halaman per halaman
            do {
                // `num` hanya boleh di halaman ke-2+ (saat next_page_token ada)
                // Gunakan data_id jika tersedia, fallback ke place_id
                $params = [
                    'engine' => 'google_maps_reviews',
                    'api_key' => $apiKey,
                    'hl' => 'id',
                    'gl' => 'id',
                    'sort_by' => 'newestFirst',
                ];

                if ($dataId) {
                    $params['data_id'] = $dataId;
                } else {
                    $params['place_id'] = $placeId;
                }

                if ($nextPageToken) {
                    $params['next_page_token'] = $nextPageToken;
                    $params['num'] = 10;
                }

                $response = Http::timeout(20)->get('https://serpapi.com/search.json', $params);
                $apiCallsUsed++;

                $data = $response->json();

                // Cek error dari SerpAPI (termasuk 401 Invalid API key)
                if (isset($data['error'])) {
                    return [
                        'synced' => 0,
                        'it_related' => 0,
                        'error' => 'SerpAPI error: '.$data['error'],
                    ];
                }

                if (! $response->successful()) {
                    Log::error('SerpAPI HTTP error: '.$response->status());

                    return [
                        'synced' => 0,
                        'it_related' => 0,
                        'error' => 'HTTP error '.$response->status().' dari SerpAPI.',
                    ];
                }

                $reviews = $data['reviews'] ?? [];
                if (empty($reviews)) {
                    break;
                }

                $allReviews = array_merge($allReviews, $reviews);

                // Ambil token untuk halaman berikutnya
                $nextPageToken = $data['serpapi_pagination']['next_page_token'] ?? null;
                $page++;

            } while ($nextPageToken && $page < $maxPages);

            // Simpan semua review ke database lokal
            $synced = 0;
            $itRelated = 0;

            foreach ($allReviews as $review) {
                $userLink = $review['user']['link'] ?? '';
                $isoDate = $review['iso_date'] ?? null;
                $reviewId = md5($userLink.$isoDate);

                $text = $review['snippet'] ?? '';
                $rating = (int) ($review['rating'] ?? 0);

                // Parse tanggal
                $reviewTime = $isoDate
                    ? Carbon::parse($isoDate)
                    : now();

                $this->saveReview([
                    'review_id' => $reviewId,
                    'author_name' => $review['user']['name'] ?? 'Anonim',
                    'author_url' => $userLink ?: null,
                    'profile_photo_url' => $review['user']['thumbnail'] ?? null,
                    'rating' => $rating,
                    'text' => $text,
                    'language' => 'id',
                    'review_time' => $reviewTime,
                ]);

                $synced++;

                $itResult = $this->analyzeItRelation($text);
                if ($itResult['is_it_related']) {
                    $itRelated++;
                }
            }

            return [
                'synced' => $synced,
                'it_related' => $itRelated,
                'api_calls_used' => $apiCallsUsed,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('SerpApiService error: '.$e->getMessage());

            return ['synced' => 0, 'it_related' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cek sisa kuota SerpAPI (berapa request tersisa bulan ini)
     */
    public function checkQuota(): array
    {
        $apiKey = config('services.serpapi.key');

        if (! $apiKey) {
            return ['error' => 'API key tidak ditemukan'];
        }

        try {
            $response = Http::timeout(10)->get('https://serpapi.com/account', [
                'api_key' => $apiKey,
            ]);

            if (! $response->successful()) {
                return ['error' => 'Gagal cek kuota'];
            }

            $data = $response->json();

            return [
                'plan' => $data['plan_name'] ?? '-',
                'searches_used' => $data['this_month_usage'] ?? 0,
                'searches_left' => $data['plan_searches_left'] ?? 0,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
