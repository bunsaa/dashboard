<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, BedDouble, ChevronLeft, ChevronRight, RefreshCw, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';
import { bed as bedRoute } from '@/routes';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'BED RSUD Tarakan',
                href: props.currentTeam ? bedRoute(props.currentTeam.slug).url : '/',
            },
        ],
    }),
});

type BedRow = {
    NamaRuangan: string;
    TotalBed: number;
    Terisi: number;
    Kosong: number;
    LakiLaki: number;
    Perempuan: number;
};

type Patient = {
    NamaRuangan: string;
    BedID: string;
    NoRekamMedis: string;
    NamaPasien: string;
    NamaDPJP: string;
    TglMasuk: string;
    Jaminan: string;
    Sex: string;
};

const props = defineProps<{
    data: BedRow[];
    patients: Patient[];
    updatedAt: string;
    error: string | null;
}>();

// ── Pasien dikelompokkan per ruangan ────────────────────────────────────────
const patientsByRoom = computed(() => {
    const map = new Map<string, Patient[]>();
    for (const p of props.patients) {
        const list = map.get(p.NamaRuangan) ?? [];
        list.push(p);
        map.set(p.NamaRuangan, list);
    }
    return map;
});

// ── Search filter ───────────────────────────────────────────────────────────
const search = ref('');

const filteredData = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.data;
    return props.data.filter(r => r.NamaRuangan.toLowerCase().includes(q));
});

// ── Pagination ──────────────────────────────────────────────────────────────
const PAGE_SIZE = 10;
const currentPage = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredData.value.length / PAGE_SIZE)));

const paginated = computed(() =>
    filteredData.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE),
);

// reset page jika filter berubah
function onSearch() { currentPage.value = 1; }

function prevPage() { if (currentPage.value > 1) currentPage.value--; }
function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++; }

// ── Totals ──────────────────────────────────────────────────────────────────
const totals = computed(() => ({
    TotalBed:  props.data.reduce((s, r) => s + Number(r.TotalBed),  0),
    Terisi:    props.data.reduce((s, r) => s + Number(r.Terisi),    0),
    Kosong:    props.data.reduce((s, r) => s + Number(r.Kosong),    0),
    LakiLaki:  props.data.reduce((s, r) => s + Number(r.LakiLaki),  0),
    Perempuan: props.data.reduce((s, r) => s + Number(r.Perempuan), 0),
}));

const occupancyRate = computed(() =>
    totals.value.TotalBed > 0
        ? Math.round((totals.value.Terisi / totals.value.TotalBed) * 100)
        : 0,
);

// ── Helpers ─────────────────────────────────────────────────────────────────
function pct(row: BedRow): number {
    const total = Number(row.TotalBed);
    return total > 0 ? Math.round((Number(row.Terisi) / total) * 100) : 0;
}

function barColor(p: number): string {
    if (p >= 90) return 'bg-red-500';
    if (p >= 70) return 'bg-amber-400';
    return 'bg-emerald-500';
}

function textColor(p: number): string {
    if (p >= 90) return 'text-red-600 dark:text-red-400';
    if (p >= 70) return 'text-amber-600 dark:text-amber-400';
    return 'text-emerald-600 dark:text-emerald-400';
}

// ── Modal detail pasien ─────────────────────────────────────────────────────
const modalRoom    = ref<BedRow | null>(null);
const modalPage    = ref(1);
const MODAL_PAGE_SIZE = 10;

function parseDateDMY(s: string): number {
    const [d, m, y] = s.split('/');
    return new Date(+y, +m - 1, +d).getTime();
}

const modalDateSort = ref<'desc' | 'asc'>('desc');

function toggleDateSort() {
    modalDateSort.value = modalDateSort.value === 'desc' ? 'asc' : 'desc';
    modalPage.value = 1;
}

const modalPatients = computed(() => {
    const list = modalRoom.value ? (patientsByRoom.value.get(modalRoom.value.NamaRuangan) ?? []) : [];
    return [...list].sort((a, b) =>
        modalDateSort.value === 'desc'
            ? parseDateDMY(b.TglMasuk) - parseDateDMY(a.TglMasuk)
            : parseDateDMY(a.TglMasuk) - parseDateDMY(b.TglMasuk),
    );
});

const modalTotalPages = computed(() =>
    Math.max(1, Math.ceil(modalPatients.value.length / MODAL_PAGE_SIZE)),
);

const modalPaginated = computed(() =>
    modalPatients.value.slice(
        (modalPage.value - 1) * MODAL_PAGE_SIZE,
        modalPage.value * MODAL_PAGE_SIZE,
    ),
);

function openModal(row: BedRow) {
    modalRoom.value = row;
    modalPage.value = 1;
    modalDateSort.value = 'desc';
}
function closeModal() { modalRoom.value = null; }

function modalPrev() { if (modalPage.value > 1) modalPage.value--; }
function modalNext() { if (modalPage.value < modalTotalPages.value) modalPage.value++; }

// ── Auto-refresh setiap 60 detik ────────────────────────────────────────────
const refreshing = ref(false);

function refresh() {
    refreshing.value = true;
    router.reload({ onFinish: () => { refreshing.value = false; } });
}

const timer = setInterval(refresh, 60_000);
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Head title="BED RSUD Tarakan" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 xl:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">BED RSUD Tarakan Jakarta</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Data real-time ketersediaan tempat tidur · diperbarui: {{ props.updatedAt }}
                </p>
            </div>
            <button
                :disabled="refreshing"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="refresh"
            >
                <RefreshCw :size="15" :class="refreshing ? 'animate-spin' : ''" />
                Refresh
            </button>
        </div>

        <!-- Error alert -->
        <div
            v-if="props.error"
            class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            <AlertCircle :size="18" class="mt-0.5 shrink-0" />
            <p>{{ props.error }}</p>
        </div>

        <!-- Summary cards -->
        <div v-if="props.data.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Total Bed</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ totals.TotalBed.toLocaleString('id-ID') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Terisi</p>
                <p class="mt-1 text-2xl font-bold" :class="textColor(occupancyRate)">{{ totals.Terisi.toLocaleString('id-ID') }}</p>
                <p class="text-xs text-gray-400">{{ occupancyRate }}% BOR</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Tersedia</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ totals.Kosong.toLocaleString('id-ID') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Pasien (L / P)</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                    <span class="text-blue-600 dark:text-blue-400">{{ totals.LakiLaki }}</span>
                    <span class="mx-1 text-gray-300">/</span>
                    <span class="text-pink-600 dark:text-pink-400">{{ totals.Perempuan }}</span>
                </p>
            </div>
        </div>

        <!-- Search + info -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <input
                v-model="search"
                type="text"
                placeholder="Cari nama ruangan…"
                class="h-9 w-56 rounded-lg border border-gray-200 bg-white px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                @input="onSearch"
            />
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ filteredData.length }} ruangan · klik nama ruangan untuk lihat detail pasien
            </span>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-auto max-h-[60svh]">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="w-10 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Ruangan</th>
                            <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Total</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Terisi</th>
                            <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Kosong</th>
                            <th class="px-4 py-3 text-center font-semibold text-blue-600 dark:text-blue-400">L</th>
                            <th class="px-4 py-3 text-center font-semibold text-pink-600 dark:text-pink-400">P</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="!filteredData.length">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                {{ props.error ? 'Tidak ada data — koneksi ke database TARAKAN gagal.' : 'Tidak ada data.' }}
                            </td>
                        </tr>
                        <tr
                            v-for="(row, idx) in paginated"
                            :key="row.NamaRuangan"
                            class="cursor-pointer transition-colors hover:bg-blue-50 dark:hover:bg-blue-900/10"
                            @click="openModal(row)"
                        >
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                {{ (currentPage - 1) * PAGE_SIZE + idx + 1 }}
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <BedDouble :size="14" class="shrink-0 text-gray-400" />
                                    <span class="font-medium text-blue-700 underline-offset-2 hover:underline dark:text-blue-400">
                                        {{ row.NamaRuangan }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-gray-700 dark:text-gray-300">
                                {{ Number(row.TotalBed) }}
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="barColor(pct(row))"
                                            :style="{ width: pct(row) + '%' }"
                                        />
                                    </div>
                                    <span class="tabular-nums" :class="textColor(pct(row))">
                                        {{ Number(row.Terisi) }}
                                        <span class="text-xs text-gray-400">({{ pct(row) }}%)</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ Number(row.Kosong) }}
                            </td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-blue-600 dark:text-blue-400">
                                {{ Number(row.LakiLaki) }}
                            </td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-pink-600 dark:text-pink-400">
                                {{ Number(row.Perempuan) }}
                            </td>
                        </tr>
                    </tbody>
                    <!-- Total row -->
                    <tfoot v-if="filteredData.length" class="border-t-2 border-gray-300 dark:border-gray-600">
                        <tr class="bg-gray-50 font-semibold dark:bg-gray-700/50">
                            <td colspan="2" class="px-4 py-2.5 text-gray-700 dark:text-gray-300">TOTAL ({{ props.data.length }} ruangan)</td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-gray-700 dark:text-gray-300">
                                {{ totals.TotalBed }}
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="barColor(occupancyRate)"
                                            :style="{ width: occupancyRate + '%' }"
                                        />
                                    </div>
                                    <span class="tabular-nums" :class="textColor(occupancyRate)">
                                        {{ totals.Terisi }}
                                        <span class="text-xs text-gray-400">({{ occupancyRate }}%)</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-emerald-600 dark:text-emerald-400">{{ totals.Kosong }}</td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-blue-600 dark:text-blue-400">{{ totals.LakiLaki }}</td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-pink-600 dark:text-pink-400">{{ totals.Perempuan }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination footer -->
            <div
                v-if="totalPages > 1"
                class="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700"
            >
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Hal {{ currentPage }} / {{ totalPages }} &nbsp;·&nbsp; {{ filteredData.length }} ruangan
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
                                ? 'border-blue-500 bg-blue-500 text-white'
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
        </div>

        <!-- ── Modal detail pasien ─────────────────────────────────────────── -->
        <Teleport to="body">
        <div
            v-if="modalRoom"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            @click.self="closeModal"
        >
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal" />

            <!-- Panel -->
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900">

                <!-- Modal header -->
                <div class="flex items-center justify-between bg-gradient-to-r from-blue-600 to-blue-500 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20">
                            <BedDouble :size="20" class="text-white" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-white">{{ modalRoom.NamaRuangan }}</h2>
                            <div class="mt-0.5 flex items-center gap-3 text-xs text-blue-100">
                                <span>{{ Number(modalRoom.Terisi) }} pasien terisi</span>
                                <span class="flex items-center gap-1">
                                    <span class="inline-block h-2 w-2 rounded-full bg-blue-200"></span>
                                    ♂ Laki-laki: {{ Number(modalRoom.LakiLaki) }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="inline-block h-2 w-2 rounded-full bg-pink-300"></span>
                                    ♀ Perempuan: {{ Number(modalRoom.Perempuan) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <button
                        class="rounded-lg p-1.5 text-white/70 transition-colors hover:bg-white/20 hover:text-white"
                        @click="closeModal"
                    >
                        <X :size="20" />
                    </button>
                </div>

                <!-- Modal body -->
                <div class="overflow-y-auto">
                    <div v-if="!modalPatients.length" class="px-6 py-14 text-center text-sm text-gray-400 dark:text-gray-500">
                        Tidak ada data pasien untuk ruangan ini.
                    </div>
                    <table v-else class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                        <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="w-12 px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bed</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No RM</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama Pasien</th>
                                <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">JK</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">DPJP</th>
                                <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide dark:text-gray-400">
                                    <button
                                        class="inline-flex items-center gap-1 transition-colors"
                                        :class="'text-blue-600 dark:text-blue-400 hover:text-blue-700'"
                                        @click="toggleDateSort"
                                    >
                                        Tgl Masuk
                                        <span class="text-base leading-none">{{ modalDateSort === 'desc' ? '↓' : '↑' }}</span>
                                    </button>
                                </th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jaminan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr
                                v-for="(p, i) in modalPaginated"
                                :key="p.BedID"
                                class="transition-colors hover:bg-blue-50/50 dark:hover:bg-blue-900/10"
                            >
                                <td class="px-5 py-4 text-gray-400 dark:text-gray-500">
                                    {{ (modalPage - 1) * MODAL_PAGE_SIZE + i + 1 }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ p.BedID }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {{ p.NoRekamMedis }}
                                </td>
                                <td class="px-5 py-4 font-medium text-gray-900 dark:text-gray-100">
                                    {{ p.NamaPasien }}
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full text-sm font-bold"
                                        :class="p.Sex === 'M'
                                            ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300'
                                            : 'bg-pink-100 text-pink-600 dark:bg-pink-900/40 dark:text-pink-300'"
                                        :title="p.Sex === 'M' ? 'Laki-laki' : 'Perempuan'"
                                    >{{ p.Sex === 'M' ? '♂' : '♀' }}</span>
                                </td>
                                <td class="px-5 py-4 text-gray-700 dark:text-gray-300">{{ p.NamaDPJP }}</td>
                                <td class="px-5 py-4 text-center tabular-nums text-gray-600 dark:text-gray-400">{{ p.TglMasuk }}</td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="p.Jaminan === 'UMUM'
                                            ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'"
                                    >{{ p.Jaminan }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Modal footer: pagination -->
                <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50/80 px-6 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        <template v-if="modalTotalPages > 1">
                            Hal {{ modalPage }} / {{ modalTotalPages }} &nbsp;·&nbsp;
                        </template>
                        {{ modalPatients.length }} pasien
                    </span>
                    <div v-if="modalTotalPages > 1" class="flex items-center gap-1">
                        <button
                            :disabled="modalPage === 1"
                            class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                            @click="modalPrev"
                        >
                            <ChevronLeft :size="14" />
                        </button>
                        <template v-for="p in modalTotalPages" :key="p">
                            <button
                                v-if="p === 1 || p === modalTotalPages || Math.abs(p - modalPage) <= 1"
                                class="inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded border px-1 text-xs transition-colors"
                                :class="p === modalPage
                                    ? 'border-blue-500 bg-blue-500 text-white'
                                    : 'border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700'"
                                @click="modalPage = p"
                            >
                                {{ p }}
                            </button>
                            <span v-else-if="p === 2 && modalPage > 3" class="px-0.5 text-xs text-gray-400">…</span>
                            <span v-else-if="p === modalTotalPages - 1 && modalPage < modalTotalPages - 2" class="px-0.5 text-xs text-gray-400">…</span>
                        </template>
                        <button
                            :disabled="modalPage === modalTotalPages"
                            class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                            @click="modalNext"
                        >
                            <ChevronRight :size="14" />
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </Teleport>

    </div>
</template>
