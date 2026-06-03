<?php

namespace App\Services\Concerns;

trait AnalyzesItKeywords
{
    /**
     * Keyword kuat: selalu menandai review sebagai terkait IT, berapapun ratingnya.
     * Ini adalah kata yang secara spesifik merujuk ke sistem/teknologi IT.
     */
    private array $itKeywordsStrong = [
        // Umum IT
        'it', 'sistem', 'system', 'teknologi', 'technology', 'digital',
        // Aplikasi & Software
        'aplikasi', 'app', 'software', 'program', 'website', 'web', 'portal',
        'simrs', 'sirs', 'emr', 'ehr',
        // Pendaftaran & Antrian Online
        'pendaftaran online', 'daftar online', 'antrian online',
        'booking', 'reservasi', 'jadwal online', 'nomor antrian', 'tiket antrian',
        // Integrasi & Sinkronisasi Sistem
        'integrasi', 'terintegrasi', 'sinkron', 'sinkronisasi',
        'nge-link', 'tidak link', 'gak link', 'tidak nyambung', 'gak nyambung',
        'tidak tersambung', 'tidak sinkron', 'realtime', 'real-time',
        // Jaringan
        'wifi', 'wi-fi', 'internet', 'jaringan', 'network',
        // Hardware
        'komputer', 'computer', 'pc', 'laptop', 'printer', 'mesin antrian',
        'layar', 'monitor', 'scanner',
        // Error & Gangguan Sistem
        'error', 'eror', 'bug', 'crash', 'down', 'offline',
        // Kuota & Kapasitas Sistem
        'kuota', 'kapasitas sistem', 'limit sistem', 'penuh sistem',
        // Data & Rekam Medis Digital
        'rekam medis', 'medical record', 'berkas digital', 'input data', 'data pasien', 'database',
        // Layanan Digital
        'telemedicine', 'telemedis', 'konsultasi online', 'resep digital',
    ];

    /**
     * Keyword lemah: hanya menandai sebagai terkait IT jika rating <= 3 (negatif/netral).
     * Kata-kata ini juga sering muncul di review positif (bukan keluhan IT).
     * Contoh: "saya pikir pelayanan akan LELET tapi ternyata cepat" -> tidak perlu ditandai IT.
     */
    private array $itKeywordsWeak = [
        // Masalah Performa (sering muncul di konteks positif juga)
        'lambat', 'lemot', 'lelet', 'slow', 'hang', 'macet', 'ngadat', 'ngehang',
        // Masalah Umum (bisa muncul di konteks non-IT)
        'gangguan', 'trouble', 'tidak bisa', 'gabisa', 'gak bisa',
        'tidak berfungsi', 'rusak', 'mati', 'gak jalan', 'tidak jalan',
        // Kata generik (ambiguous)
        'online', 'koneksi', 'sinyal', 'alat',
    ];

    private array $recommendations = [
        'wifi|internet|jaringan|koneksi|sinyal' => [
            'title' => 'Kendala Jaringan/Koneksi Internet',
            'short' => ['Tim IT segera lakukan pengecekan dan restart perangkat jaringan (router/access point) di area yang dikeluhkan pasien. Pastikan koneksi ke ISP dalam kondisi normal dan tidak ada gangguan pada backbone jaringan rumah sakit. Catat titik lokasi keluhan untuk mempercepat investigasi di lapangan.'],
            'medium' => ['Tim IT lakukan audit menyeluruh cakupan sinyal WiFi di seluruh area rumah sakit menggunakan heat map tools, lalu tambahkan access point di titik-titik dengan sinyal lemah. Tinjau ulang paket bandwidth bersama ISP dan pertimbangkan peningkatan kapasitas jika utilisasi sudah melebihi 70%.'],
            'long' => ['Tim IT implementasikan koneksi internet redundan dengan dua ISP berbeda yang dilengkapi mekanisme failover otomatis, sehingga jika satu jalur terputus layanan tetap berjalan tanpa gangguan. Susun SLA dengan masing-masing ISP dan siapkan prosedur eskalasi yang jelas jika terjadi gangguan berkepanjangan.'],
        ],
        'pendaftaran online|daftar online|antrian online|booking|reservasi' => [
            'title' => 'Kendala Sistem Pendaftaran/Antrian Online',
            'short' => ['Tim IT segera periksa status server dan baca log error pada aplikasi pendaftaran online untuk mengidentifikasi akar penyebab gangguan. Lakukan restart service yang bermasalah dan verifikasi bahwa sistem kembali dapat diakses oleh pasien sebelum jam layanan dimulai.'],
            'medium' => ['Tim IT lakukan load testing pada sistem pendaftaran untuk mengetahui batas kapasitas server saat lonjakan pengguna terjadi di jam sibuk, kemudian lakukan optimasi konfigurasi server dan database. Pertimbangkan implementasi antrian virtual atau sistem caching untuk meringankan beban server pada jam-jam puncak.'],
            'long' => ['Tim IT rencanakan pengembangan arsitektur sistem pendaftaran yang lebih skalabel, dilengkapi notifikasi otomatis kepada pasien via WhatsApp atau SMS untuk konfirmasi, perubahan jadwal, dan pengingat. Integrasikan sistem antrian online dengan sistem manajemen kapasitas poli agar kuota pendaftaran selalu sinkron dengan kondisi aktual.'],
        ],
        'lambat|lemot|lelet|slow|hang|macet' => [
            'title' => 'Kendala Performa Sistem Lambat',
            'short' => ['Tim IT segera pantau penggunaan CPU, memori, dan disk pada server melalui tools monitoring — identifikasi proses atau layanan yang mengonsumsi resource secara berlebihan dan lakukan penanganan segera seperti restart proses atau pembersihan disk. Dokumentasikan waktu kejadian untuk analisis pola lebih lanjut.'],
            'medium' => ['Tim IT pasang sistem monitoring performa server secara real-time (seperti Grafana/Prometheus atau tools sejenis) agar kelambatan dapat terdeteksi sebelum berdampak ke pengguna. Lakukan analisis bottleneck pada lapisan database, aplikasi, dan jaringan, kemudian terapkan optimasi sesuai temuan.'],
            'long' => ['Tim IT susun roadmap upgrade infrastruktur server secara bertahap berdasarkan data pertumbuhan penggunaan layanan digital, termasuk evaluasi kebutuhan perpindahan ke arsitektur cloud atau hybrid. Pastikan setiap fase upgrade dilakukan dengan migrasi yang terencana agar tidak mengganggu operasional layanan.'],
        ],
        'error|eror|gangguan|bug|crash|tidak bisa|tidak berfungsi|rusak|down|offline' => [
            'title' => 'Kendala Error atau Gangguan Sistem',
            'short' => ['Tim IT segera periksa log sistem dan application server untuk mengidentifikasi penyebab error secara spesifik, kemudian lakukan perbaikan langsung atau rollback ke versi sebelumnya jika gangguan disebabkan oleh pembaruan terbaru. Informasikan status gangguan dan perkiraan pemulihan kepada unit terkait agar operasional dapat diantisipasi.'],
            'medium' => ['Tim IT susun dan terapkan SOP penanganan insiden IT yang mencakup klasifikasi tingkat gangguan (kritis, sedang, rendah), alur eskalasi yang jelas, serta target waktu penyelesaian untuk setiap level. Pastikan seluruh anggota tim IT memahami dan dapat menjalankan prosedur tersebut secara mandiri.'],
            'long' => ['Tim IT rancang dan implementasikan mekanisme high availability untuk sistem-sistem kritikal, mencakup replikasi layanan, load balancing, dan prosedur disaster recovery yang telah diuji secara berkala. Lakukan disaster recovery drill minimal dua kali setahun untuk memastikan kesiapan tim dan sistem dalam menghadapi gangguan besar.'],
        ],
        'simrs|sirs|emr|ehr|rekam medis|medical record' => [
            'title' => 'Kendala Sistem Rekam Medis atau SIMRS',
            'short' => ['Tim IT segera periksa status layanan SIMRS melalui dashboard monitoring dan investigasi log aplikasi untuk menemukan penyebab gangguan. Lakukan restart service yang bermasalah, atau jika gangguan serius, segera pulihkan dari backup terakhir yang valid sambil berkoordinasi dengan vendor SIMRS.'],
            'medium' => ['Tim IT jadwalkan sesi review performa SIMRS bersama vendor untuk mengevaluasi modul-modul yang paling sering bermasalah dan menyusun daftar prioritas perbaikan pada pembaruan berikutnya. Pastikan kontrak pemeliharaan dengan vendor mencakup SLA respon yang memadai untuk gangguan di jam operasional.'],
            'long' => ['Tim IT rencanakan migrasi SIMRS ke versi terbaru secara bertahap, dengan memastikan integrasi penuh ke sistem antrian, laboratorium, farmasi, dan apotek sebelum go-live. Libatkan pengguna kunci dari setiap unit dalam proses UAT untuk memastikan semua alur kerja berjalan dengan baik sebelum sistem baru digunakan secara penuh.'],
        ],
        'komputer|computer|pc|laptop|printer|layar|monitor|scanner' => [
            'title' => 'Kendala Perangkat/Alat IT',
            'short' => ['Tim IT segera datangi lokasi yang melaporkan gangguan, periksa kondisi perangkat, dan sediakan unit pengganti dari stok cadangan agar operasional tidak terhenti. Catat detail kerusakan untuk keperluan klaim garansi atau perbaikan lebih lanjut oleh teknisi.'],
            'medium' => ['Tim IT buat dan jalankan jadwal preventive maintenance rutin untuk seluruh perangkat IT di semua unit layanan, serta lakukan inventarisasi kondisi dan perkiraan usia pakai setiap perangkat. Gunakan data ini untuk mengidentifikasi perangkat yang perlu segera diganti sebelum menyebabkan gangguan operasional.'],
            'long' => ['Tim IT susun rencana pembaruan perangkat IT secara berkala dan masukkan ke dalam anggaran tahunan rumah sakit, dengan standarisasi spesifikasi minimal untuk setiap jenis perangkat di seluruh unit. Pertimbangkan skema leasing atau perjanjian pembaruan berkala dengan vendor untuk menjaga ketersediaan perangkat yang selalu dalam kondisi prima.'],
        ],
        'aplikasi|app|software|website|web|portal' => [
            'title' => 'Kendala Aplikasi atau Website',
            'short' => ['Tim IT segera reproduksi masalah yang dilaporkan di lingkungan staging, periksa log error aplikasi secara mendetail, lalu lakukan perbaikan bug dan deploy hotfix ke production setelah pengujian singkat. Komunikasikan status perbaikan kepada pengguna yang terdampak agar mereka mengetahui kapan layanan kembali normal.'],
            'medium' => ['Tim IT lakukan code review pada fitur atau modul yang bermasalah untuk menemukan akar penyebab bug, kemudian rilis pembaruan perbaikan mengikuti prosedur deployment yang terencana dan berisiko rendah. Tingkatkan cakupan automated testing agar bug serupa dapat terdeteksi lebih awal sebelum sampai ke production.'],
            'long' => ['Tim IT susun roadmap pengembangan versi baru aplikasi yang mencakup peningkatan performa, penguatan keamanan, dan perbaikan pengalaman pengguna berdasarkan masukan dari lapangan. Implementasikan pipeline CI/CD yang terstruktur agar proses pengembangan, pengujian, dan deployment dapat berjalan lebih cepat dan andal.'],
        ],
        'integrasi|sinkron|nge-link|tidak link|gak link|tidak nyambung|gak nyambung|tidak tersambung|tidak sinkron|realtime' => [
            'title' => 'Kendala Sinkronisasi Sistem Online dan Loket',
            'short' => ['Tim IT segera periksa status sinkronisasi data antara sistem pendaftaran online dan sistem loket, lakukan force sync manual jika ditemukan ketidaksesuaian data antrian. Identifikasi titik putusnya aliran data — apakah di sisi API, database, atau konfigurasi middleware — untuk perbaikan cepat.'],
            'medium' => ['Tim IT perbaiki mekanisme integrasi antar sistem dengan menambahkan error handling yang lebih baik dan terapkan alerting otomatis kepada tim jika proses sinkronisasi gagal atau tertunda lebih dari batas waktu yang ditentukan. Buat dashboard monitoring status integrasi yang dapat dipantau secara real-time oleh tim IT.'],
            'long' => ['Tim IT rancang ulang arsitektur integrasi menggunakan pendekatan message queue atau event-driven architecture agar sinkronisasi data antar sistem berjalan andal, tahan gangguan, dan dapat dipulihkan otomatis jika terjadi kegagalan. Dokumentasikan seluruh aliran integrasi untuk mempermudah pemeliharaan dan onboarding anggota tim baru.'],
        ],
        'kuota|kapasitas sistem|limit sistem|penuh sistem' => [
            'title' => 'Kendala Kuota atau Kapasitas Sistem',
            'short' => ['Tim IT segera tinjau dan sesuaikan konfigurasi batas kuota pada sistem pendaftaran agar sesuai dengan kapasitas aktual setiap poli, serta aktifkan mekanisme otomatis yang mencegah pasien mendaftar melebihi slot yang tersedia. Pastikan perubahan konfigurasi diuji terlebih dahulu di environment staging sebelum diterapkan ke production.'],
            'medium' => ['Tim IT implementasikan sistem manajemen kuota yang dinamis dan tersinkron secara real-time dengan kapasitas aktual setiap poli dan unit layanan, termasuk mempertimbangkan faktor hari libur, cuti dokter, dan kapasitas ruangan. Libatkan koordinasi dengan bagian pelayanan medis agar data kapasitas selalu akurat.'],
            'long' => ['Tim IT kembangkan modul analitik kapasitas layanan yang mampu memprediksi kebutuhan kuota berdasarkan data historis dan tren penggunaan, sehingga manajemen dapat membuat keputusan perencanaan kapasitas yang lebih tepat. Integrasikan modul ini dengan sistem pelaporan rumah sakit untuk mendukung perencanaan anggaran dan sumber daya secara komprehensif.'],
        ],
    ];

    /**
     * Analisis apakah review terkait IT.
     * - Keyword kuat: trigger IT tanpa memandang rating.
     * - Keyword lemah: hanya trigger IT jika rating <= 3 (review negatif/netral).
     */
    public function analyzeItRelation(string $text, int $rating = 5): array
    {
        $textLower = strtolower($text);
        $foundKeywords = [];

        // Keyword kuat - selalu match
        foreach ($this->itKeywordsStrong as $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/u';
            if (preg_match($pattern, $textLower)) {
                $foundKeywords[] = $keyword;
            }
        }

        // Keyword lemah - hanya match jika review negatif/netral (rating <= 3)
        if ($rating <= 3) {
            foreach ($this->itKeywordsWeak as $keyword) {
                $pattern = '/\b' . preg_quote($keyword, '/') . '\b/u';
                if (preg_match($pattern, $textLower)) {
                    $foundKeywords[] = $keyword;
                }
            }
        }

        return [
            'is_it_related' => $foundKeywords !== [],
            'keywords_found' => array_unique($foundKeywords),
        ];
    }

    public function generateRecommendation(string $text): string
    {
        $textLower = strtolower($text);
        $matchedRecs = [];

        foreach ($this->recommendations as $pattern => $rec) {
            $keywords = explode('|', $pattern);
            foreach ($keywords as $kw) {
                $kwPattern = '/\b' . preg_quote($kw, '/') . '\b/u';
                if (preg_match($kwPattern, $textLower)) {
                    $matchedRecs[] = $rec;
                    break 2; // hanya ambil 1 kategori pertama yang cocok
                }
            }
        }

        if (empty($matchedRecs)) {
            $matchedRecs[] = [
                'title' => 'Kendala Layanan IT',
                'short' => ['Tim IT segera melakukan investigasi terhadap keluhan yang masuk dengan mengumpulkan informasi detail dari pengguna, memeriksa log sistem yang relevan, serta mengidentifikasi komponen mana yang berpotensi menjadi penyebab masalah. Pastikan juga tidak ada layanan lain yang sedang mengalami gangguan serupa.'],
                'medium' => ['Tim IT dokumentasikan temuan investigasi secara lengkap ke dalam sistem pencatatan insiden IT, analisis apakah keluhan serupa pernah terjadi sebelumnya, dan susun langkah perbaikan sistematis yang dapat mencegah masalah terulang di masa mendatang.'],
                'long' => ['Tim IT gunakan data insiden yang terkumpul sebagai bahan evaluasi untuk meningkatkan ketahanan sistem secara menyeluruh, termasuk mempertimbangkan pembaruan arsitektur, penguatan prosedur pemeliharaan rutin, dan peningkatan kapasitas tim agar keandalan layanan IT rumah sakit terus meningkat dari waktu ke waktu.'],
            ];
        }

        $lines = [];
        foreach ($matchedRecs as $rec) {
            $lines[] = 'TITLE:'.$rec['title'];
            $lines[] = 'PENDEK:Jangka Pendek — Segera Ditangani';
            foreach ($rec['short'] as $action) {
                $lines[] = '- '.$action;
            }
            $lines[] = 'MENENGAH:Jangka Menengah — 1 s.d. 3 Bulan';
            foreach ($rec['medium'] as $action) {
                $lines[] = '- '.$action;
            }
            $lines[] = 'PANJANG:Jangka Panjang — 3 s.d. 12 Bulan';
            foreach ($rec['long'] as $action) {
                $lines[] = '- '.$action;
            }
        }

        return implode("\n", $lines);
    }

    public function determineSentiment(int $rating): string
    {
        if ($rating >= 4) {
            return 'positive';
        }
        if ($rating === 3) {
            return 'neutral';
        }

        return 'negative';
    }

    public function saveReview(array $data): void
    {
        // Keyword matching — selalu dijalankan sebagai suplemen/fallback
        $itKeyword = $this->analyzeItRelation($data['text'], $data['rating']);

        $isItRelated       = $itKeyword['is_it_related'];
        $recommendation    = null;
        $isAiRecommendation = false;

        /** @var \App\Services\OpenAiService $openAi */
        $openAi = app(\App\Services\OpenAiService::class);

        // Prioritas: biarkan AI membaca teks dan menentukan relevansi IT
        $aiResult = $openAi->classifyAndRecommend($data['text'], $data['rating']);

        if ($aiResult !== null) {
            // AI berhasil mengklasifikasi — pakai hasilnya
            $isItRelated = $aiResult['is_it_related'];

            if ($isItRelated && $aiResult['recommendation'] !== null) {
                $recommendation    = $aiResult['recommendation'];
                $isAiRecommendation = true;
            } elseif ($isItRelated && $data['rating'] <= 3) {
                // AI bilang IT-related tapi tidak generate rekomendasi (rating > 3 path atau gagal parse)
                // Fallback ke rule-based
                $recommendation = $this->generateRecommendation($data['text']);
            }
        } else {
            // AI tidak tersedia / teks kosong → gunakan keyword matching
            if ($isItRelated && $data['rating'] <= 3 && ! empty($data['text'])) {
                $recommendation = $this->generateRecommendation($data['text']);
            }
        }

        \App\Models\GoogleReview::updateOrCreate(
            ['review_id' => $data['review_id']],
            array_merge($data, [
                'is_it_related'     => $isItRelated,
                'it_keywords_found' => $itKeyword['keywords_found'],
                'recommendation'    => $recommendation,
                'is_ai_recommendation' => $isAiRecommendation,
                'sentiment'         => $this->determineSentiment($data['rating']),
            ])
        );
    }
}
