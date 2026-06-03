<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, ChevronLeft, ChevronRight, Download, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import downloadReportRoutes from '@/routes/download-report';
import rawatJalanRoutes from '@/routes/download-report/rawat-jalan';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Download Report',
                href: props.currentTeam ? downloadReportRoutes.rawatJalan(props.currentTeam.slug).url : '/',
            },
            {
                title: 'Rawat Jalan',
                href: props.currentTeam ? downloadReportRoutes.rawatJalan(props.currentTeam.slug).url : '/',
            },
        ],
    }),
});

type RekapRow = {
    No: number;
    NamaPoli: string;
    NamaDokter: string;
} & Record<string, number | string>;

type RekapData = {
    rows: RekapRow[];
    error: string | null;
} | null; // null = masih loading

const props = defineProps<{
    tahun: number;
    fromYear: number;
    years: number[];
    rekapData: RekapData;
    poliList?: string[];
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);

// ── State loading / error dari prop deferred ───────────────────────────────
const isLoading = computed(() => props.rekapData === null);
const rekap     = computed(() => props.rekapData?.rows ?? []);
const error     = computed(() => props.rekapData?.error ?? null);

// ── Year filter (server-side) ──────────────────────────────────────────────
const MIN_YEAR = Math.min(2019, props.tahun - 5);
const yearOptions = computed(() => {
    const opts: number[] = [];
    for (let y = props.tahun; y >= MIN_YEAR; y--) {
        opts.push(y);
    }
    return opts;
});

const localFromYear = ref<number>(props.fromYear);
watch(() => props.fromYear, (v) => { localFromYear.value = v; });

function applyYearFilter() {
    if (!currentTeam.value) return;
    router.get(
        downloadReportRoutes.rawatJalan(currentTeam.value.slug).url,
        { fromYear: localFromYear.value },
        { only: ['rekapData', 'fromYear', 'years'], preserveScroll: true },
    );
}

// ── Filter poli (client-side) ─────────────────────────────────────────────
const filterInput = ref<string>('');
function resetFilter() { filterInput.value = ''; }

// ── Agregasi per-poli ──────────────────────────────────────────────────────
type PoliEntry = { NamaPoli: string } & Record<string, number>;

const rekapByPoli = computed(() => {
    const map = new Map<string, PoliEntry>();
    for (const row of rekap.value) {
        const poli = row.NamaPoli as string;
        const cur = map.get(poli);
        if (cur) {
            for (const y of props.years) {
                const key = `year_${y}`;
                cur[key] = (cur[key] ?? 0) + Number((row as Record<string, unknown>)[key] ?? 0);
            }
        } else {
            const entry: PoliEntry = { NamaPoli: poli };
            for (const y of props.years) {
                const key = `year_${y}`;
                entry[key] = Number((row as Record<string, unknown>)[key] ?? 0);
            }
            map.set(poli, entry);
        }
    }
    return Array.from(map.values());
});

const filteredRekap = computed(() => {
    const val = filterInput.value.trim().toLowerCase();
    if (!val) return rekapByPoli.value;
    return rekapByPoli.value.filter(row => row.NamaPoli.toLowerCase().includes(val));
});

// ── Pagination ─────────────────────────────────────────────────────────────
const PAGE_SIZE = 10;
const currentPage = ref(1);
watch([() => props.rekapData, filterInput], () => { currentPage.value = 1; });

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRekap.value.length / PAGE_SIZE)));
const paginated  = computed(() =>
    filteredRekap.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE),
);
function prevPage() { if (currentPage.value > 1) currentPage.value--; }
function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++; }

// ── URL Excel ──────────────────────────────────────────────────────────────
const excelAllUrl = computed(() => {
    if (!currentTeam.value) return '#';
    const q = filterInput.value.trim();
    return rawatJalanRoutes.excel(currentTeam.value.slug, {
        query: { fromYear: props.fromYear, ...(q ? { q } : {}) },
    }).url;
});

function poliExcelUrl(namaPoli: string): string {
    if (!currentTeam.value) return '#';
    return rawatJalanRoutes.excel(currentTeam.value.slug, {
        query: { fromYear: props.fromYear, poli: namaPoli },
    }).url;
}

function triggerDownload(url: string) { window.location.href = url; }
</script>

<template>
    <Head title="Rawat Jalan" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 xl:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Laporan Kunjungan Pasien Rawat Jalan</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Data kunjungan rawat jalan RSUD Tarakan tahun {{ props.fromYear }}–{{ props.tahun }}
                </p>
            </div>
            <button
                :disabled="isLoading || !!error"
                :class="(isLoading || !!error) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-emerald-700'"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                @click="triggerDownload(excelAllUrl)"
            >
                <Download :size="16" />
                Download Semua
            </button>
        </div>

        <!-- Error alert -->
        <div
            v-if="error"
            class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            <AlertCircle :size="18" class="mt-0.5 shrink-0" />
            <p>{{ error }}</p>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <!-- Year filter -->
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Dari Tahun:</label>
                <select
                    v-model="localFromYear"
                    class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                >
                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                </select>
                <button
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    @click="applyYearFilter"
                >
                    Terapkan
                </button>

                <span class="text-gray-300 dark:text-gray-600">|</span>

                <!-- Poli search -->
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Cari Poli:</label>
                <input
                    v-model="filterInput"
                    list="poli-datalist-rj"
                    type="text"
                    placeholder="Ketik sebagian nama poli…"
                    class="h-9 w-64 rounded-lg border border-gray-200 bg-white px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                />
                <datalist id="poli-datalist-rj">
                    <template v-if="props.poliList">
                        <option v-for="poli in props.poliList" :key="poli" :value="poli" />
                    </template>
                </datalist>
                <button
                    v-if="filterInput"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-sm text-gray-500 hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                    @click="resetFilter"
                >
                    <X :size="14" />
                    Reset
                </button>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ filteredRekap.length }} dari {{ rekapByPoli.length }} poli
            </span>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <!-- Skeleton loading -->
            <div v-if="isLoading" class="p-6 space-y-3">
                <div class="h-4 w-1/3 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                <div v-for="i in 6" :key="i" class="h-9 animate-pulse rounded bg-gray-100 dark:bg-gray-700/60" />
            </div>

            <template v-else>
                <div class="overflow-auto max-h-[60svh]">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="w-12 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Poli</th>
                                <th
                                    v-for="y in props.years"
                                    :key="y"
                                    class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Jml Pasien {{ y }}
                                </th>
                                <th class="w-14 px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Excel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="!filteredRekap.length">
                                <td :colspan="3 + props.years.length" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                    {{ error ? 'Tidak ada data — koneksi ke database TARAKAN gagal.' : 'Tidak ada data.' }}
                                </td>
                            </tr>
                            <tr
                                v-for="(row, idx) in paginated"
                                :key="row.NamaPoli"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30"
                            >
                                <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                    {{ (currentPage - 1) * PAGE_SIZE + idx + 1 }}
                                </td>
                                <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ row.NamaPoli }}</td>
                                <td
                                    v-for="y in props.years"
                                    :key="y"
                                    class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300"
                                >
                                    {{ (row[`year_${y}`] as number ?? 0).toLocaleString('id-ID') }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <button
                                        :title="`Download Excel — ${row.NamaPoli}`"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                                        @click="triggerDownload(poliExcelUrl(row.NamaPoli))"
                                    >
                                        <Download :size="14" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div
                    v-if="totalPages > 1"
                    class="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700"
                >
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Hal {{ currentPage }} / {{ totalPages }} &nbsp;·&nbsp; {{ filteredRekap.length }} poli
                    </span>
                    <div class="flex items-center gap-1">
                        <button
                            :disabled="currentPage === 1"
                            class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                            @click="prevPage"
                        >
                            <ChevronLeft :size="14" />
                        </button>
                        <template v-for="p in totalPages" :key="p">
                            <button
                                v-if="p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1"
                                class="inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded border px-1 text-xs transition-colors"
                                :class="p === currentPage
                                    ? 'border-emerald-500 bg-emerald-500 text-white'
                                    : 'border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700'"
                                @click="currentPage = p"
                            >
                                {{ p }}
                            </button>
                            <span v-else-if="p === 2 && currentPage > 3" class="px-0.5 text-xs text-gray-400">…</span>
                            <span v-else-if="p === totalPages - 1 && currentPage < totalPages - 2" class="px-0.5 text-xs text-gray-400">…</span>
                        </template>
                        <button
                            :disabled="currentPage === totalPages"
                            class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                            @click="nextPage"
                        >
                            <ChevronRight :size="14" />
                        </button>
                    </div>
                </div>
            </template>
        </div>

    </div>
</template>
