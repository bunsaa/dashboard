<?php

namespace App\Http\Controllers\Renkin;

use App\Http\Controllers\Controller;
use App\Models\GoogleReview;
use App\Services\OpenAiService;
use App\Services\GooglePlacesService;
use App\Services\SerpApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoogleReviewController extends Controller
{
    public function __construct(
        private SerpApiService $serpApiService,
        private GooglePlacesService $placesService,
        private OpenAiService $openAiService,
    ) {}

    public function index(Request $request): Response
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        // Statistik review IT per bulan yang dipilih
        $itReviews = GoogleReview::itRelated()
            ->byMonth($year, $month)
            ->orderBy('review_time', 'desc')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'author_name' => $r->author_name,
                'rating' => $r->rating,
                'text' => $r->text,
                'review_time' => $r->review_time->format('d M Y'),
                'review_time_full' => $r->review_time->translatedFormat('l, d F Y H:i'),
                'it_keywords_found' => $r->it_keywords_found,
                'recommendation' => $r->recommendation,
                'is_ai_recommendation' => $r->is_ai_recommendation,
                'sentiment' => $r->sentiment,
                'profile_photo_url' => $r->profile_photo_url,
            ]);

        // Statistik global IT reviews
        $totalItReviews = GoogleReview::itRelated()->count();
        $thisMonthIt = GoogleReview::itRelated()->byMonth($year, $month)->count();
        $negativeItThisMonth = GoogleReview::itRelated()->negative()->byMonth($year, $month)->count();
        $avgRatingIt = GoogleReview::itRelated()->byMonth($year, $month)->avg('rating');

        // Data chart: IT reviews per bulan (12 bulan terakhir)
        $monthlyChart = [];
        for ($m = 11; $m >= 0; $m--) {
            $date = now()->subMonths($m);
            $monthlyChart[] = [
                'label' => $date->format('M Y'),
                'total' => GoogleReview::itRelated()->byMonth($date->year, $date->month)->count(),
                'negative' => GoogleReview::itRelated()->negative()->byMonth($date->year, $date->month)->count(),
            ];
        }

        // Distribusi rating IT reviews bulan ini
        $ratingDistribution = [];
        for ($r = 1; $r <= 5; $r++) {
            $ratingDistribution[$r] = GoogleReview::itRelated()
                ->byMonth($year, $month)
                ->where('rating', $r)
                ->count();
        }

        // Top keyword IT yang paling sering muncul bulan ini
        $allKeywords = GoogleReview::itRelated()
            ->byMonth($year, $month)
            ->whereNotNull('it_keywords_found')
            ->pluck('it_keywords_found')
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->toArray();

        // Deteksi sumber API yang aktif
        $apiSource = config('services.serpapi.key')
            ? 'SerpAPI (scraper)'
            : (config('services.google.places_api_key') ? 'Google Places API' : 'Belum dikonfigurasi');

        return Inertia::render('Renkin/GoogleReviews', [
            'itReviews' => $itReviews,
            'stats' => [
                'total_it_reviews' => $totalItReviews,
                'this_month_it' => $thisMonthIt,
                'negative_it_this_month' => $negativeItThisMonth,
                'avg_rating_it' => round((float) $avgRatingIt, 1),
            ],
            'monthlyChart' => $monthlyChart,
            'ratingDistribution' => $ratingDistribution,
            'topKeywords' => $allKeywords,
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'apiSource' => $apiSource,
        ]);
    }

    /**
     * Sync review — otomatis pakai SerpAPI jika key ada, fallback ke Google Places API
     */
    public function sync(Request $request): \Illuminate\Http\JsonResponse
    {
        $maxPages = (int) $request->get('max_pages', 3);

        // Prioritas: SerpAPI dulu (lebih banyak review, gratis 100 req/bulan)
        if (config('services.serpapi.key')) {
            $result = $this->serpApiService->fetchAndSyncReviews($maxPages);
        } elseif (config('services.google.places_api_key')) {
            $result = $this->placesService->fetchAndSyncReviews();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada API yang dikonfigurasi. Tambahkan SERPAPI_KEY atau GOOGLE_PLACES_API_KEY di file .env',
            ], 422);
        }

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'message' => 'Sync gagal: '.$result['error'],
            ], 422);
        }

        $source = config('services.serpapi.key') ? 'SerpAPI' : 'Google Places API';
        $itInfo = $result['it_related'] > 0 ? " ({$result['it_related']} terkait IT)" : '';

        // Auto-generate rekomendasi AI untuk review yang belum memilikinya
        $aiUpdated = 0;
        if ($this->openAiService->isConfigured()) {
            $pendingReviews = GoogleReview::itRelated()
                ->negative()
                ->whereNotNull('recommendation')
                ->where('is_ai_recommendation', false)
                ->get();

            foreach ($pendingReviews as $review) {
                $aiRec = $this->openAiService->generateItRecommendation($review->text ?? '', $review->rating);
                if (! empty($aiRec)) {
                    $review->update(['recommendation' => $aiRec, 'is_ai_recommendation' => true]);
                    $aiUpdated++;
                }
            }
        }

        $aiInfo = $aiUpdated > 0 ? " + {$aiUpdated} rekomendasi AI digenerate." : '';

        return response()->json([
            'success' => true,
            'message' => "Berhasil sync {$result['synced']} review via {$source}{$itInfo}.{$aiInfo}",
        ]);
    }

    /**
     * Cek sisa kuota SerpAPI
     */
    public function quota(): \Illuminate\Http\JsonResponse
    {
        if (! config('services.serpapi.key')) {
            return response()->json(['error' => 'SerpAPI key belum dikonfigurasi.'], 422);
        }

        $quota = $this->serpApiService->checkQuota();

        return response()->json($quota);
    }

    public function seedDummy(): \Illuminate\Http\JsonResponse
    {
        $this->placesService->seedDummyReviews();

        // Auto-generate rekomendasi AI untuk review dummy yang belum memilikinya
        $aiUpdated = 0;
        if ($this->openAiService->isConfigured()) {
            $pendingReviews = GoogleReview::itRelated()
                ->negative()
                ->whereNotNull('recommendation')
                ->where('is_ai_recommendation', false)
                ->get();

            foreach ($pendingReviews as $review) {
                $aiRec = $this->openAiService->generateItRecommendation($review->text ?? '', $review->rating);
                if (! empty($aiRec)) {
                    $review->update(['recommendation' => $aiRec, 'is_ai_recommendation' => true]);
                    $aiUpdated++;
                }
            }
        }

        $aiInfo = $aiUpdated > 0 ? " {$aiUpdated} rekomendasi AI telah digenerate." : '';

        return response()->json([
            'success' => true,
            'message' => "Data dummy berhasil diisi untuk testing.{$aiInfo}",
        ]);
    }

    /**
     * Regenerate rekomendasi menggunakan Claude AI untuk semua review IT negatif
     * yang belum memiliki rekomendasi AI.
     */
    public function regenerateAiRecommendations(): \Illuminate\Http\JsonResponse
    {
        if (! $this->openAiService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Groq AI belum dikonfigurasi. Tambahkan GROQ_API_KEY di file .env',
            ], 422);
        }

        $reviews = GoogleReview::itRelated()
            ->negative()
            ->whereNotNull('recommendation')
            ->where('is_ai_recommendation', false)
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Berhasil: Semua rekomendasi sudah menggunakan AI.',
            ]);
        }

        $updated = 0;
        foreach ($reviews as $review) {
            $aiRec = $this->openAiService->generateItRecommendation($review->text ?? '', $review->rating);
            if (! empty($aiRec)) {
                $review->update([
                    'recommendation' => $aiRec,
                    'is_ai_recommendation' => true,
                ]);
                $updated++;
            }
        }

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate rekomendasi AI. Pastikan GROQ_API_KEY valid dan coba lagi.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil generate rekomendasi AI untuk {$updated} dari {$reviews->count()} review.",
        ]);
    }
}
