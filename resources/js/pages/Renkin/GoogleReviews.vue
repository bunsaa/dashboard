<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BotMessageSquare,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Database,
    MessageSquare,
    RefreshCw,
    Star,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import renkin from '@/routes/renkin';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
            },
            {
                title: 'Renkin',
                href: props.currentTeam ? renkin.googleReviews(props.currentTeam.slug) : '/',
            },
            {
                title: 'Monitoring Google Reviews IT',
                href: props.currentTeam ? renkin.googleReviews(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const props = defineProps<{
    itReviews: Array<{
        id: number;
        author_name: string;
        rating: number;
        text: string;
        review_time: string;
        review_time_full: string;
        it_keywords_found: string[];
        recommendation: string | null;
        is_ai_recommendation: boolean;
        sentiment: string;
        profile_photo_url: string | null;
    }>;
    stats: {
        total_it_reviews: number;
        this_month_it: number;
        negative_it_this_month: number;
        avg_rating_it: number;
    };
    monthlyChart: Array<{
        label: string;
        total: number;
        negative: number;
    }>;
    ratingDistribution: Record<number, number>;
    topKeywords: Record<string, number>;
    apiSource: string;
    filters: {
        year: number;
        month: number;
    };
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team);

// State
const selectedYear = ref(props.filters.year.toString());
const selectedMonth = ref(props.filters.month.toString());
const isSyncing = ref(false);
const isSeeding = ref(false);
const syncMessage = ref('');
const expandedReviews = ref<Set<number>>(new Set());

// Months list
const months = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const years = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 3 }, (_, i) => ({
        value: (currentYear - i).toString(),
        label: (currentYear - i).toString(),
    }));
});

const maxChartValue = computed(() => {
    const max = Math.max(...props.monthlyChart.map((m) => m.total), 1);
    return max;
});

// Apply filter
function applyFilter() {
    router.get(
        renkin.googleReviews(currentTeam.value.slug).url,
        { year: selectedYear.value, month: selectedMonth.value },
        { preserveState: true, preserveScroll: true },
    );
}

// Sync from Google
async function syncFromGoogle() {
    isSyncing.value = true;
    syncMessage.value = '';
    try {
        const response = await fetch(renkin.googleReviews.sync(currentTeam.value.slug).url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        });
        const data = await response.json();
        syncMessage.value = data.message;
        if (data.success) {
            router.reload();
        }
    } catch {
        syncMessage.value = 'Gagal menghubungi server.';
    } finally {
        isSyncing.value = false;
    }
}

// Seed dummy data
async function seedDummy() {
    isSeeding.value = true;
    syncMessage.value = '';
    try {
        const response = await fetch(renkin.googleReviews.seedDummy(currentTeam.value.slug).url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        });
        const data = await response.json();
        syncMessage.value = data.message;
        if (data.success) {
            router.reload();
        }
    } catch {
        syncMessage.value = 'Gagal menghubungi server.';
    } finally {
        isSeeding.value = false;
    }
}

function toggleReview(id: number) {
    if (expandedReviews.value.has(id)) {
        expandedReviews.value.delete(id);
    } else {
        expandedReviews.value.add(id);
    }
}

function sentimentColor(sentiment: string) {
    if (sentiment === 'positive') return 'text-green-600 dark:text-green-400';
    if (sentiment === 'negative') return 'text-red-600 dark:text-red-400';
    return 'text-yellow-600 dark:text-yellow-400';
}

function ratingColor(rating: number) {
    if (rating >= 4) return 'text-green-600';
    if (rating === 3) return 'text-yellow-500';
    return 'text-red-500';
}

function stars(rating: number) {
    return '★'.repeat(rating) + '☆'.repeat(5 - rating);
}

const monthLabel = computed(() => months.find((m) => m.value === selectedMonth.value)?.label ?? '');

function parseRecommendation(text: string) {
    const tiers: { label: string; title: string; items: string[]; style: string; badgeStyle: string }[] = [];
    const config: Record<string, { label: string; style: string; badgeStyle: string }> = {
        PENDEK: {
            label: 'Jangka Pendek',
            style: 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100',
            badgeStyle: 'bg-red-600 text-white',
        },
        MENENGAH: {
            label: 'Jangka Menengah',
            style: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
            badgeStyle: 'bg-amber-500 text-white',
        },
        PANJANG: {
            label: 'Jangka Panjang',
            style: 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100',
            badgeStyle: 'bg-blue-600 text-white',
        },
    };

    let current: (typeof tiers)[0] | null = null;
    for (const raw of text.split('\n')) {
        const line = raw.trim();
        if (!line) continue;

        const tierMatch = line.match(/^(PENDEK|MENENGAH|PANJANG):(.+)$/);
        if (tierMatch) {
            const key = tierMatch[1];
            current = { ...config[key], title: tierMatch[2], items: [] };
            tiers.push(current);
            continue;
        }
        if (line.startsWith('- ') && current) {
            current.items.push(line.slice(2));
        }
    }
    return tiers.slice(0, 3);
}
</script>

<template>
    <Head title="Renkin - Monitoring Google Reviews IT" />

    <div class="flex flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Monitoring Google Reviews - IT</h1>
                <p class="text-sm text-muted-foreground">
                    RSUD Tarakan DKI Jakarta &mdash; Rekapitulasi review terkait layanan IT per bulan
                </p>
                <div class="mt-1.5 flex items-center gap-2">
                    <Badge
                        variant="outline"
                        class="text-xs"
                        :class="apiSource.includes('SerpAPI') ? 'border-blue-400 text-blue-600 dark:text-blue-400' : apiSource.includes('Google') ? 'border-green-400 text-green-600 dark:text-green-400' : 'border-orange-400 text-orange-600 dark:text-orange-400'"
                    >
                        Sumber: {{ apiSource }}
                    </Badge>
                    <span v-if="apiSource === 'Belum dikonfigurasi'" class="text-xs text-orange-500">
                        — Tambahkan SERPAPI_KEY di .env untuk mulai sync
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" :disabled="isSyncing" @click="syncFromGoogle">
                    <RefreshCw class="mr-1.5 h-4 w-4" :class="{ 'animate-spin': isSyncing }" />
                    {{ isSyncing ? 'Menyinkron...' : 'Sync Google Reviews' }}
                </Button>
                <!-- <Button variant="secondary" size="sm" :disabled="isSeeding" @click="seedDummy">
                    <Database class="mr-1.5 h-4 w-4" />
                    {{ isSeeding ? 'Mengisi...' : 'Isi Data Dummy' }}
                </Button> -->
            </div>
        </div>

        <!-- Sync message -->
        <div
            v-if="syncMessage"
            class="flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm"
            :class="
                syncMessage.includes('Berhasil') || syncMessage.includes('berhasil')
                    ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-400'
                    : 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-400'
            "
        >
            <CheckCircle2 v-if="syncMessage.includes('Berhasil') || syncMessage.includes('berhasil')" class="h-4 w-4 shrink-0" />
            <AlertTriangle v-else class="h-4 w-4 shrink-0" />
            {{ syncMessage }}
        </div>

        <!-- Filter -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Filter Periode</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-muted-foreground">Tahun</label>
                        <Select v-model="selectedYear">
                            <SelectTrigger class="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="y in years" :key="y.value" :value="y.value">
                                    {{ y.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-muted-foreground">Bulan</label>
                        <Select v-model="selectedMonth">
                            <SelectTrigger class="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="m in months" :key="m.value" :value="m.value">
                                    {{ m.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button @click="applyFilter">Tampilkan</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Stats Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Total Review IT</CardTitle>
                    <MessageSquare class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-bold">{{ stats.total_it_reviews }}</div>
                    <p class="text-xs text-muted-foreground">Semua waktu</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Review IT Bulan Ini</CardTitle>
                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-bold">{{ stats.this_month_it }}</div>
                    <p class="text-xs text-muted-foreground">{{ monthLabel }} {{ filters.year }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Review Negatif IT</CardTitle>
                    <TrendingDown class="h-4 w-4 text-red-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-bold text-red-500">{{ stats.negative_it_this_month }}</div>
                    <p class="text-xs text-muted-foreground">Rating ≤ 3 bulan ini</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Rata-rata Rating IT</CardTitle>
                    <Star class="h-4 w-4 text-yellow-500" />
                </CardHeader>
                <CardContent>
                    <div
                        class="text-3xl font-bold"
                        :class="stats.avg_rating_it >= 4 ? 'text-green-600' : stats.avg_rating_it >= 3 ? 'text-yellow-500' : 'text-red-500'"
                    >
                        {{ stats.avg_rating_it || '-' }}
                    </div>
                    <p class="text-xs text-muted-foreground">dari 5.0</p>
                </CardContent>
            </Card>
        </div>

        <!-- Chart + Top Keywords -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Trend 12 bulan -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">Tren Review IT (12 Bulan Terakhir)</CardTitle>
                    <CardDescription>Jumlah review terkait IT per bulan</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex h-40 items-end gap-1">
                        <div
                            v-for="bar in monthlyChart"
                            :key="bar.label"
                            class="group relative flex flex-1 flex-col items-center gap-0.5"
                        >
                            <!-- Tooltip -->
                            <div
                                class="absolute -top-8 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs text-white group-hover:block"
                            >
                                {{ bar.label }}: {{ bar.total }} review ({{ bar.negative }} negatif)
                            </div>
                            <div class="flex w-full flex-col items-center justify-end gap-0.5" style="height: 100%">
                                <div
                                    class="w-full rounded-t bg-primary transition-all"
                                    :style="{ height: maxChartValue > 0 ? (bar.total / maxChartValue) * 100 + '%' : '0%', minHeight: bar.total > 0 ? '4px' : '0' }"
                                />
                            </div>
                            <span class="mt-1 rotate-45 text-[10px] text-muted-foreground">{{
                                bar.label.split(' ')[0]
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Top Keywords -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Keyword IT Terbanyak</CardTitle>
                    <CardDescription>Bulan {{ monthLabel }} {{ filters.year }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="Object.keys(topKeywords).length === 0" class="text-sm text-muted-foreground">
                        Belum ada data.
                    </div>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="(count, keyword) in topKeywords"
                            :key="keyword"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="font-medium capitalize">{{ keyword }}</span>
                            <Badge variant="secondary">{{ count }}x</Badge>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <!-- Rating Distribution -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Distribusi Rating Review IT &mdash; {{ monthLabel }} {{ filters.year }}</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <div v-for="r in [5, 4, 3, 2, 1]" :key="r" class="flex items-center gap-3">
                        <span class="w-16 text-sm font-medium" :class="ratingColor(r)">{{ stars(r) }}</span>
                        <div class="h-3 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="r >= 4 ? 'bg-green-500' : r === 3 ? 'bg-yellow-500' : 'bg-red-500'"
                                :style="{
                                    width:
                                        stats.this_month_it > 0
                                            ? ((ratingDistribution[r] ?? 0) / stats.this_month_it) * 100 + '%'
                                            : '0%',
                                }"
                            />
                        </div>
                        <span class="w-6 text-right text-sm text-muted-foreground">{{ ratingDistribution[r] ?? 0 }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Daftar Review IT -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">
                    Daftar Review Terkait IT &mdash; {{ monthLabel }} {{ filters.year }}
                    <Badge class="ml-2">{{ itReviews.length }}</Badge>
                </CardTitle>
                <CardDescription>
                    Review dari Google Maps RSUD Tarakan yang mengandung kata kunci terkait layanan IT. Review negatif disertai
                    rekomendasi penanganan.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="itReviews.length === 0" class="flex flex-col items-center gap-3 py-12 text-center text-muted-foreground">
                    <MessageSquare class="h-10 w-10 opacity-40" />
                    <div>
                        <p class="font-medium">Tidak ada review IT pada periode ini.</p>
                        <p class="text-sm">Coba sync dari Google atau isi data dummy untuk testing.</p>
                    </div>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="review in itReviews"
                        :key="review.id"
                        class="overflow-hidden rounded-lg border transition-all"
                        :class="review.rating <= 3 ? 'border-red-200 dark:border-red-900' : 'border-border'"
                    >
                        <!-- Review Header -->
                        <div
                            class="flex cursor-pointer items-start justify-between gap-3 p-4"
                            @click="toggleReview(review.id)"
                        >
                            <div class="flex flex-1 items-start gap-3">
                                <!-- Avatar -->
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                                    :class="review.rating >= 4 ? 'bg-green-500' : review.rating === 3 ? 'bg-yellow-500' : 'bg-red-500'"
                                >
                                    {{ review.author_name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-sm">{{ review.author_name }}</span>
                                        <span class="text-xs" :class="ratingColor(review.rating)">
                                            {{ stars(review.rating) }}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            class="text-xs"
                                            :class="sentimentColor(review.sentiment)"
                                        >
                                            {{ review.sentiment === 'positive' ? 'Positif' : review.sentiment === 'negative' ? 'Negatif' : 'Netral' }}
                                        </Badge>
                                        <span class="text-xs text-muted-foreground">{{ review.review_time }}</span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ review.text }}</p>
                                    <!-- Keywords -->
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        <Badge
                                            v-for="kw in review.it_keywords_found"
                                            :key="kw"
                                            variant="secondary"
                                            class="text-[10px]"
                                        >
                                            {{ kw }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 text-muted-foreground">
                                <ChevronUp v-if="expandedReviews.has(review.id)" class="h-4 w-4" />
                                <ChevronDown v-else class="h-4 w-4" />
                            </div>
                        </div>

                        <!-- Expanded Content -->
                        <div v-if="expandedReviews.has(review.id)" class="border-t bg-muted/30 px-4 pb-4 pt-3">
                            <div class="mb-2 text-xs text-muted-foreground">{{ review.review_time_full }}</div>
                            <p class="mb-4 text-sm leading-relaxed">{{ review.text }}</p>

                            <!-- Recommendation -->
                            <div v-if="review.recommendation" class="space-y-2">
                                <div class="flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
                                    <AlertTriangle class="h-4 w-4" />
                                    Rekomendasi Penanganan
                                    <span
                                        v-if="review.is_ai_recommendation"
                                        class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-700 dark:bg-violet-900 dark:text-violet-300"
                                    >
                                        <BotMessageSquare class="h-3 w-3" />
                                        AI
                                    </span>
                                </div>
                                <template v-for="(tier, i) in parseRecommendation(review.recommendation)" :key="i">
                                    <div :class="tier.style" class="rounded-lg border p-3">
                                        <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold">
                                            <span :class="tier.badgeStyle" class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide">
                                                {{ tier.label }}
                                            </span>
                                            <span>{{ tier.title }}</span>
                                        </div>
                                        <ul class="space-y-1.5">
                                            <li v-for="(item, j) in tier.items" :key="j" class="flex gap-2 text-xs leading-relaxed">
                                                <span class="mt-0.5 shrink-0 opacity-60">•</span>
                                                <span>{{ item }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
