<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Download, Eye, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import downloadReportRoutes from '@/routes/download-report';
import kunjunganDokterRoutes from '@/routes/download-report/kunjungan-dokter';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Download Report',
                href: props.currentTeam ? downloadReportRoutes.rawatJalan(props.currentTeam.slug).url : '/',
            },
            {
                title: 'Data Kunjungan Dokter',
                href: props.currentTeam ? downloadReportRoutes.kunjunganDokter(props.currentTeam.slug).url : '/',
            },
        ],
    }),
});

type DoctorRow = {
    ParamedicID: string;
    ParamedicName: string;
    JumlahRJ: number;
    JumlahRI: number;
    JumlahPasien: number;
};

type DetailRow = {
    bulan: number;
    namaBulan: string;
    tahun: number;
    JumlahRJ: number;
    JumlahRI: number;
    JumlahPasien: number;
};

type Detail = {
    paramedicId: string;
    paramedicName: string;
    rows: DetailRow[];
};

const props = defineProps<{
    fromDate: string;
    toDate: string;
    items: DoctorRow[];
    pagination: { total: number; perPage: number; currentPage: number; lastPage: number };
    detail?: Detail | null;
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);

// ── Local filter state ────────────────────────────────────────────────────────
const localFrom = ref(props.fromDate);
const localTo = ref(props.toDate);
const searchInput = ref<string>('');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

// ── Navigation ────────────────────────────────────────────────────────────────
function baseUrl(): string {
    return currentTeam.value ? downloadReportRoutes.kunjunganDokter(currentTeam.value.slug).url : '#';
}

function applyFilter() {
    router.get(baseUrl(), { fromDate: localFrom.value, toDate: localTo.value, page: 1 });
}

function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            baseUrl(),
            { fromDate: props.fromDate, toDate: props.toDate, page: 1, q: searchInput.value || undefined },
            { only: ['items', 'pagination'] },
        );
    }, 500);
}

function clearSearch() {
    searchInput.value = '';
    router.get(
        baseUrl(),
        { fromDate: props.fromDate, toDate: props.toDate, page: 1 },
        { only: ['items', 'pagination'] },
    );
}

function goToPage(pg: number) {
    router.get(
        baseUrl(),
        { fromDate: props.fromDate, toDate: props.toDate, page: pg, q: searchInput.value || undefined },
        { only: ['items', 'pagination'] },
    );
}

// ── Detail modal ──────────────────────────────────────────────────────────────
const detailLoading = ref(false);
const modalDetail = ref<Detail | null>(null);

watch(
    () => props.detail,
    (val) => {
        if (val) modalDetail.value = val;
        detailLoading.value = false;
    },
);

function openDetail(row: DoctorRow) {
    detailLoading.value = true;
    router.get(
        baseUrl(),
        { fromDate: props.fromDate, toDate: props.toDate, detailId: row.ParamedicID },
        { only: ['detail'], preserveState: true },
    );
}

function closeDetail() {
    modalDetail.value = null;
    detailLoading.value = false;
}

// ── Excel URLs ────────────────────────────────────────────────────────────────
const excelAllUrl = computed(() => {
    if (!currentTeam.value) return '#';
    return kunjunganDokterRoutes.excel(currentTeam.value.slug, {
        query: { fromDate: props.fromDate, toDate: props.toDate },
    }).url;
});

function doctorExcelUrl(row: DoctorRow): string {
    if (!currentTeam.value) return '#';
    return kunjunganDokterRoutes.excel(currentTeam.value.slug, {
        query: { fromDate: props.fromDate, toDate: props.toDate, paramedicId: row.ParamedicID },
    }).url;
}

// ── Pagination ────────────────────────────────────────────────────────────────
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

// ── Detail totals ─────────────────────────────────────────────────────────────
const detailTotalRJ = computed(() => modalDetail.value?.rows.reduce((s, r) => s + Number(r.JumlahRJ), 0) ?? 0);
const detailTotalRI = computed(() => modalDetail.value?.rows.reduce((s, r) => s + Number(r.JumlahRI), 0) ?? 0);
const detailTotal = computed(() => detailTotalRJ.value + detailTotalRI.value);
</script>

<template>
    <Head title="Data Kunjungan Dokter" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 xl:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Data Kunjungan Ke-Dokter</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Rekapitulasi kunjungan per dokter RSUD Tarakan
                </p>
            </div>
            <!-- <a
                :href="excelAllUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-emerald-700"
            >
                <Download :size="16" />
                Download Excel
            </a> -->
        </div>

        <!-- Date range filter -->
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Dari Tanggal</label>
                <input
                    v-model="localFrom"
                    type="date"
                    class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">s/d Tanggal</label>
                <input
                    v-model="localTo"
                    type="date"
                    class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                />
            </div>
            <button
                class="inline-flex h-9 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-indigo-700"
                @click="applyFilter"
            >
                Terapkan
            </button>
        </div>

        <!-- Search bar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Cari Dokter:</label>
                <div class="relative">
                    <Search :size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="Nama dokter..."
                        class="h-9 w-60 rounded-lg border border-gray-200 bg-white pl-8 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        @input="onSearchInput"
                        @keydown.enter.prevent="onSearchInput"
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
                {{ pagination.total.toLocaleString('id-ID') }} dokter
            </span>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-auto max-h-[60svh]">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="w-10 px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Dokter</th>
                            <th class="px-3 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-400">Rawat Jalan</th>
                            <th class="px-3 py-3 text-right font-semibold text-orange-600 dark:text-orange-400">Rawat Inap</th>
                            <th class="px-3 py-3 text-right font-semibold text-blue-700 dark:text-blue-400">Total</th>
                            <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="!props.items.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                Tidak ada data.
                            </td>
                        </tr>
                        <tr
                            v-for="(row, idx) in props.items"
                            :key="row.ParamedicID"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30"
                        >
                            <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">
                                {{ (pagination.currentPage - 1) * pagination.perPage + idx + 1 }}
                            </td>
                            <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ row.ParamedicName }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-400">
                                {{ Number(row.JumlahRJ).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-orange-600 dark:text-orange-400">
                                {{ Number(row.JumlahRI).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-blue-700 dark:text-blue-400">
                                {{ Number(row.JumlahPasien).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        title="Lihat detail per bulan"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-indigo-200 bg-indigo-50 text-indigo-700 transition-colors hover:bg-indigo-100 dark:border-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400"
                                        @click="openDetail(row)"
                                    >
                                        <Eye :size="14" />
                                    </button>
                                    <a
                                        :href="doctorExcelUrl(row)"
                                        title="Download Excel"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-emerald-200 bg-emerald-50 text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400"
                                    >
                                        <Download :size="14" />
                                    </a>
                                </div>
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
                    {{ pagination.total.toLocaleString('id-ID') }} dokter
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
                                ? 'border-indigo-500 bg-indigo-500 text-white'
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

    <!-- Detail Modal -->
    <Teleport to="body">
        <div
            v-if="detailLoading || modalDetail"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <!-- Overlay -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDetail" />

            <!-- Modal card -->
            <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-gray-900">

                <!-- Loading state -->
                <div v-if="detailLoading" class="flex flex-col items-center justify-center gap-3 px-6 py-16">
                    <svg class="h-8 w-8 animate-spin text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Memuat data...</span>
                </div>

                <template v-else-if="modalDetail">
                <!-- Modal header -->
                <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ modalDetail.paramedicName }}
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Periode: {{ props.fromDate }} s/d {{ props.toDate }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            v-if="currentTeam"
                            :href="kunjunganDokterRoutes.excel(currentTeam.slug, {
                                query: { fromDate: props.fromDate, toDate: props.toDate, paramedicId: modalDetail.paramedicId }
                            }).url"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                        >
                            <Download :size="12" />
                            Excel
                        </a>
                        <button
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                            @click="closeDetail"
                        >
                            <X :size="16" />
                        </button>
                    </div>
                </div>

                <!-- Modal body -->
                <div class="max-h-[60vh] overflow-auto p-6">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="sticky top-0 bg-white dark:bg-gray-900">
                            <tr>
                                <th class="pb-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">No</th>
                                <th class="pb-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Bulan</th>
                                <th class="pb-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Tahun</th>
                                <th class="pb-2 text-right text-xs font-semibold text-emerald-600 dark:text-emerald-400">Rawat Jalan</th>
                                <th class="pb-2 text-right text-xs font-semibold text-orange-500 dark:text-orange-400">Rawat Inap</th>
                                <th class="pb-2 text-right text-xs font-semibold text-blue-600 dark:text-blue-400">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-if="!modalDetail.rows.length">
                                <td colspan="6" class="py-8 text-center text-gray-400">Tidak ada data.</td>
                            </tr>
                            <tr
                                v-for="(row, idx) in modalDetail.rows"
                                :key="`${row.tahun}-${row.bulan}`"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            >
                                <td class="py-2 text-gray-400">{{ idx + 1 }}</td>
                                <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ row.namaBulan }}</td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ row.tahun }}</td>
                                <td class="py-2 text-right tabular-nums text-emerald-700 dark:text-emerald-400">
                                    {{ Number(row.JumlahRJ).toLocaleString('id-ID') }}
                                </td>
                                <td class="py-2 text-right tabular-nums text-orange-600 dark:text-orange-400">
                                    {{ Number(row.JumlahRI).toLocaleString('id-ID') }}
                                </td>
                                <td class="py-2 text-right tabular-nums font-semibold text-blue-700 dark:text-blue-400">
                                    {{ Number(row.JumlahPasien).toLocaleString('id-ID') }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="modalDetail.rows.length">
                            <tr class="bg-gray-50 dark:bg-gray-800/60">
                                <td colspan="3" class="border-t-2 border-gray-300 px-0 py-2 text-xs font-bold text-gray-700 dark:border-gray-600 dark:text-gray-300">TOTAL</td>
                                <td class="border-t-2 border-gray-300 py-2 text-right tabular-nums font-bold text-emerald-700 dark:border-gray-600 dark:text-emerald-400">
                                    {{ detailTotalRJ.toLocaleString('id-ID') }}
                                </td>
                                <td class="border-t-2 border-gray-300 py-2 text-right tabular-nums font-bold text-orange-600 dark:border-gray-600 dark:text-orange-400">
                                    {{ detailTotalRI.toLocaleString('id-ID') }}
                                </td>
                                <td class="border-t-2 border-gray-300 py-2 text-right tabular-nums font-bold text-blue-700 dark:border-gray-600 dark:text-blue-400">
                                    {{ detailTotal.toLocaleString('id-ID') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>
