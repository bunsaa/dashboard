<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { AlertCircle, ChevronLeft, ChevronRight, Download, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import downloadReportRoutes from '@/routes/download-report';
import billingNonBpjsRoutes from '@/routes/download-report/billing-non-bpjs';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Download Report',
                href: props.currentTeam ? downloadReportRoutes.rawatJalan(props.currentTeam.slug).url : '/',
            },
            {
                title: 'Billing NonBPJS',
                href: props.currentTeam ? downloadReportRoutes.billingNonBpjs(props.currentTeam.slug).url : '/',
            },
        ],
    }),
});

type BillingRow = {
    RegistrationNo: string;
    Tanggal: string;
    Jam: string;
    NoRM: string;
    NamaPasien: string;
    JenisKelamin: string;
    Jaminan: string;
    KategoriJaminan: string;
    UnitLayanan: string;
    Lokasi: string;
    Dokter: string;
    Plafond: number;
    TagihanMitra: number;
    TagihanTunai: number;
    TotalTagihanAktual: number;
    SisaTagihan: number;
};

type Cards = {
    totalKunjungan: number;
    totalTagihanMitra: number;
    totalTagihanTunai: number;
    totalTagihanAktual: number;
};

const props = defineProps<{
    tahun: number;
    bulan: number | null;
    items: BillingRow[];
    pagination: { total: number; perPage: number; currentPage: number; lastPage: number };
    cards?: Cards;
    error: string | null;
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);

usePoll(1_800_000);

const BULAN_NAMA = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const BULAN_SINGKAT = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const isPerBulan = computed(() => props.bulan !== null);

// ── Search ──────────────────────────────────────────────────────────────────
const searchInput = ref<string>('');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

// ── Navigation ───────────────────────────────────────────────────────────────
function baseUrl(): string {
    return currentTeam.value ? downloadReportRoutes.billingNonBpjs(currentTeam.value.slug).url : '#';
}

function navigate(params: Record<string, string | number | null | undefined>, partial = false) {
    const clean: Record<string, string | number> = {};
    for (const [k, v] of Object.entries(params)) {
        if (v !== null && v !== undefined && v !== '') {
            clean[k] = v as string | number;
        }
    }
    router.get(baseUrl(), clean, partial ? { only: ['items', 'pagination', 'cards'] } : {});
}

function goToYear(y: number) {
    searchInput.value = '';
    navigate({ tahun: y, bulan: props.bulan ?? undefined, page: 1 });
}

function setMode(mode: 'tahun' | 'bulan') {
    searchInput.value = '';
    const newBulan = mode === 'bulan' ? (props.bulan ?? new Date().getMonth() + 1) : undefined;
    navigate({ tahun: props.tahun, bulan: newBulan, page: 1 });
}

function goToMonth(m: number) {
    navigate({ tahun: props.tahun, bulan: m, page: 1 });
}

function goToPage(pg: number) {
    navigate({ tahun: props.tahun, bulan: props.bulan ?? undefined, page: pg, q: searchInput.value || undefined }, true);
}

function doSearch() {
    navigate({ tahun: props.tahun, bulan: props.bulan ?? undefined, page: 1, q: searchInput.value || undefined }, true);
}

function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(doSearch, 500);
}

function clearSearch() {
    searchInput.value = '';
    doSearch();
}

// ── Pagination page numbers (smart, avoids rendering 1700 buttons) ───────────
const pageNumbers = computed<(number | '...')[]>(() => {
    const { currentPage, lastPage } = props.pagination;
    if (lastPage <= 10) {
        return Array.from({ length: lastPage }, (_, i) => i + 1);
    }
    const set = new Set<number>([1, 2, lastPage - 1, lastPage]);
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(lastPage, currentPage + 2); i++) {
        set.add(i);
    }
    const sorted = Array.from(set).sort((a, b) => a - b);
    const result: (number | '...')[] = [];
    for (let i = 0; i < sorted.length; i++) {
        if (i > 0 && sorted[i] - sorted[i - 1] > 1) result.push('...');
        result.push(sorted[i]);
    }
    return result;
});

// ── Excel URL ────────────────────────────────────────────────────────────────
const excelUrl = computed(() => {
    if (!currentTeam.value) return '#';
    const opts: Record<string, string | number> = { tahun: props.tahun };
    if (props.bulan) opts.bulan = props.bulan;
    return billingNonBpjsRoutes.excel(currentTeam.value.slug, { query: opts }).url;
});

// ── Formatting ───────────────────────────────────────────────────────────────
function formatRp(val: number | null | undefined): string {
    if (!val) return 'Rp 0';
    return 'Rp ' + Math.round(Number(val)).toLocaleString('id-ID');
}
</script>

<template>
    <Head title="Billing NonBPJS" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 xl:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Laporan Billing NonBPJS</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Tagihan pasien non-BPJS RSUD Tarakan
                    <template v-if="isPerBulan">{{ BULAN_NAMA[props.bulan!] }} {{ props.tahun }}</template>
                    <template v-else>tahun {{ props.tahun }}</template>
                </p>
            </div>
            <a
                :href="excelUrl"
                :class="props.error ? 'opacity-40 pointer-events-none' : 'hover:bg-emerald-700'"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
            >
                <Download :size="16" />
                Download Excel
                <span class="text-xs opacity-80">
                    ({{ isPerBulan ? BULAN_SINGKAT[props.bulan!] + ' ' + props.tahun : props.tahun }})
                </span>
            </a>
        </div>

        <!-- Error alert -->
        <div
            v-if="props.error"
            class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            <AlertCircle :size="18" class="mt-0.5 shrink-0" />
            <p>{{ props.error }}</p>
        </div>

        <!-- Year navigator + Mode toggle -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <!-- Year navigator -->
            <div class="flex items-center gap-1">
                <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                    @click="goToYear(props.tahun - 1)"
                >
                    <ChevronLeft :size="16" />
                </button>
                <div class="flex h-8 min-w-[5rem] items-center justify-center rounded border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    {{ props.tahun }}
                </div>
                <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                    @click="goToYear(props.tahun + 1)"
                >
                    <ChevronRight :size="16" />
                </button>
            </div>

            <!-- Mode toggle -->
            <div class="flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-800">
                <button
                    class="rounded px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="!isPerBulan
                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-gray-100'
                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    @click="setMode('tahun')"
                >
                    Per Tahun
                </button>
                <button
                    class="rounded px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="isPerBulan
                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-gray-100'
                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    @click="setMode('bulan')"
                >
                    Per Bulan
                </button>
            </div>
        </div>

        <!-- Month navigator (per-bulan mode only) -->
        <div v-if="isPerBulan" class="flex items-center gap-1">
            <button
                :disabled="props.bulan === 1"
                class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                @click="goToMonth(props.bulan! - 1)"
            >
                <ChevronLeft :size="16" />
            </button>
            <div class="flex h-8 min-w-[10rem] items-center justify-center rounded border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                {{ BULAN_NAMA[props.bulan!] }} {{ props.tahun }}
            </div>
            <button
                :disabled="props.bulan === 12"
                class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                @click="goToMonth(props.bulan! + 1)"
            >
                <ChevronRight :size="16" />
            </button>
        </div>

        <!-- Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

            <!-- Card 1: Total Kunjungan -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Kunjungan</p>
                <p v-if="props.cards" class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ props.cards.totalKunjungan.toLocaleString('id-ID') }}
                </p>
                <div v-else class="mt-1 h-7 w-28 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Jumlah registrasi non-BPJS</p>
            </div>

            <!-- Card 2: Tagihan Mitra -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tagihan Mitra</p>
                <p v-if="props.cards" class="mt-1 text-xl font-bold tabular-nums text-blue-700 dark:text-blue-400">
                    {{ formatRp(props.cards.totalTagihanMitra) }}
                </p>
                <div v-else class="mt-1 h-7 w-36 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Ditanggung penjamin</p>
            </div>

            <!-- Card 3: Tagihan Tunai -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tagihan Tunai</p>
                <p v-if="props.cards" class="mt-1 text-xl font-bold tabular-nums text-indigo-700 dark:text-indigo-400">
                    {{ formatRp(props.cards.totalTagihanTunai) }}
                </p>
                <div v-else class="mt-1 h-7 w-36 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Ditanggung pasien</p>
            </div>

            <!-- Card 4: Total Tagihan Aktual -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Tagihan Aktual</p>
                <p v-if="props.cards" class="mt-1 text-xl font-bold tabular-nums text-orange-600 dark:text-orange-400">
                    {{ formatRp(props.cards.totalTagihanAktual) }}
                </p>
                <div v-else class="mt-1 h-7 w-36 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Nilai tagihan sesungguhnya</p>
            </div>

        </div>

        <!-- Search bar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Cari:</label>
                <div class="relative">
                    <Search :size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="No. Reg, RM, Nama, Jaminan, Dokter, Unit..."
                        class="h-9 w-72 rounded-lg border border-gray-200 bg-white pl-8 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        @input="onSearchInput"
                        @keydown.enter.prevent="doSearch"
                    />
                </div>
                <button
                    v-if="searchInput"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-sm text-gray-500 hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                    @click="clearSearch"
                >
                    <X :size="14" />
                    Reset
                </button>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ pagination.total.toLocaleString('id-ID') }} record
            </span>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-auto max-h-[60svh]">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="w-10 px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No. Registrasi</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tanggal</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Jam</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No. RM</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Pasien</th>
                            <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Jenis Kelamin</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Jaminan</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Kategori</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Unit Layanan</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Lokasi</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Dokter</th>
                            <th class="px-3 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Plafond</th>
                            <th class="px-3 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Tagihan Mitra</th>
                            <th class="px-3 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Tagihan Tunai</th>
                            <th class="px-3 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total Tagihan</th>
                            <th class="px-3 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Sisa Tagihan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="!props.items.length">
                            <td colspan="17" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                {{ props.error ? 'Tidak ada data — koneksi ke database TARAKAN gagal.' : 'Tidak ada data.' }}
                            </td>
                        </tr>
                        <tr
                            v-for="(row, idx) in props.items"
                            :key="row.RegistrationNo"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30"
                        >
                            <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">
                                {{ (pagination.currentPage - 1) * pagination.perPage + idx + 1 }}
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs text-gray-700 dark:text-gray-300">{{ row.RegistrationNo }}</td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ row.Tanggal }}</td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ row.Jam }}</td>
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ row.NoRM }}</td>
                            <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ row.NamaPasien }}</td>
                            <td class="px-3 py-2.5 text-center text-gray-700 dark:text-gray-300">{{ row.JenisKelamin }}</td>
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ row.Jaminan }}</td>
                            <td class="px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400">{{ row.KategoriJaminan }}</td>
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ row.UnitLayanan }}</td>
                            <td class="px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400">{{ row.Lokasi }}</td>
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ row.Dokter }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{ Number(row.Plafond).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{ Number(row.TagihanMitra).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{ Number(row.TagihanTunai).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-medium text-gray-900 dark:text-gray-100">
                                {{ Number(row.TotalTagihanAktual).toLocaleString('id-ID') }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-right tabular-nums"
                                :class="Number(row.SisaTagihan) < 0 ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300'"
                            >
                                {{ Number(row.SisaTagihan).toLocaleString('id-ID') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination footer -->
            <div
                v-if="pagination.lastPage > 1"
                class="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700"
            >
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Hal {{ pagination.currentPage }} / {{ pagination.lastPage }}
                    &nbsp;·&nbsp;
                    {{ pagination.total.toLocaleString('id-ID') }} record
                </span>
                <div class="flex items-center gap-1">
                    <button
                        :disabled="pagination.currentPage === 1"
                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                        @click="goToPage(pagination.currentPage - 1)"
                    >
                        <ChevronLeft :size="14" />
                    </button>
                    <template v-for="p in pageNumbers" :key="p">
                        <span v-if="p === '...'" class="px-0.5 text-xs text-gray-400">…</span>
                        <button
                            v-else
                            class="inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded border px-1 text-xs transition-colors"
                            :class="p === pagination.currentPage
                                ? 'border-emerald-500 bg-emerald-500 text-white'
                                : 'border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700'"
                            @click="goToPage(p)"
                        >
                            {{ p }}
                        </button>
                    </template>
                    <button
                        :disabled="pagination.currentPage === pagination.lastPage"
                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                        @click="goToPage(pagination.currentPage + 1)"
                    >
                        <ChevronRight :size="14" />
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
