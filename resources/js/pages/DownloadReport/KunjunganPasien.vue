<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { AlertCircle, Download, FileText, Moon, Sun, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import downloadReportRoutes from '@/routes/download-report';
import kunjunganPasienRoutes from '@/routes/download-report/kunjungan-pasien';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Download Report',
                href: props.currentTeam ? downloadReportRoutes.rawatJalan(props.currentTeam.slug).url : '/',
            },
            {
                title: 'Kunjungan Pasien',
                href: props.currentTeam ? downloadReportRoutes.kunjunganPasien(props.currentTeam.slug).url : '/',
            },
        ],
    }),
});

type PasienRow = {
    RegistrationNo: string;
    RegistrationDate: string;
    RegistrationTime: string;
    NoRekamMedis: string;
    NamaPasien: string;
    Umur: number;
    JK: string;
    NamaPoli: string;
    NamaDokter: string;
    Penjamin: string;
    NoKartu: string;
    NoSEP: string;
    JamSlot: string | null;
    KeteranganWaktu: 'PAGI' | 'SORE';
};

type ItemsData = { rows: PasienRow[]; error: string | null } | null;

const props = defineProps<{
    tanggal: string;
    items: ItemsData;
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);

// ── State loading / error ──────────────────────────────────────────────────
const isLoading = computed(() => props.items === null);
const allRows   = computed(() => props.items?.rows ?? []);
const error     = computed(() => props.items?.error ?? null);

// ── Ringkasan cards ────────────────────────────────────────────────────────
const totalPasien = computed(() => allRows.value.length);
const pagiRows    = computed(() => allRows.value.filter(r => r.KeteranganWaktu === 'PAGI'));
const soreRows    = computed(() => allRows.value.filter(r => r.KeteranganWaktu === 'SORE'));

// ── Filter waktu (PAGI / SORE / semua) ────────────────────────────────────
const filterWaktu = ref<'SEMUA' | 'PAGI' | 'SORE'>('SEMUA');

const filteredRows = computed(() => {
    if (filterWaktu.value === 'PAGI') return pagiRows.value;
    if (filterWaktu.value === 'SORE') return soreRows.value;
    return allRows.value;
});

// ── Pagination ─────────────────────────────────────────────────────────────
const PAGE_SIZE   = 15;
const currentPage = ref(1);
watch([() => props.items, filterWaktu], () => { currentPage.value = 1; });

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / PAGE_SIZE)));
const paginated  = computed(() =>
    filteredRows.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE),
);
function prevPage() { if (currentPage.value > 1) currentPage.value--; }
function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++; }

// ── Download URLs ──────────────────────────────────────────────────────────
const excelUrl = computed(() => {
    if (!currentTeam.value) return '#';
    return kunjunganPasienRoutes.excel(currentTeam.value.slug).url;
});

const payslipUrl = computed(() => {
    if (!currentTeam.value) return '#';
    return kunjunganPasienRoutes.payslip(currentTeam.value.slug).url;
});

function triggerDownload(url: string) { window.location.href = url; }
</script>

<template>
    <Head title="Kunjungan Pasien" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 xl:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Kunjungan Pasien Rawat Jalan</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Data kunjungan hari ini — {{ props.tanggal }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <!-- Download Laporan Harian -->
                <button
                    :disabled="isLoading || !!error"
                    :class="(isLoading || !!error) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-emerald-700'"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                    @click="triggerDownload(excelUrl)"
                >
                    <Download :size="15" />
                    Laporan Harian
                </button>
                <!-- Download Payslip Sore -->
                <button
                    :disabled="isLoading || !!error || soreRows.length === 0"
                    :class="(isLoading || !!error || soreRows.length === 0) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-700'"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    @click="triggerDownload(payslipUrl)"
                >
                    <FileText :size="15" />
                    Payslip Sore
                </button>
            </div>
        </div>

        <!-- Error alert -->
        <div
            v-if="error"
            class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            <AlertCircle :size="18" class="mt-0.5 shrink-0" />
            <p>{{ error }}</p>
        </div>

        <!-- Cards ringkasan -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Total -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Pasien</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <span v-if="isLoading" class="inline-block h-8 w-16 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                            <template v-else>{{ totalPasien.toLocaleString('id-ID') }}</template>
                        </p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <Users :size="22" />
                    </div>
                </div>
            </div>

            <!-- Pagi -->
            <div
                class="cursor-pointer rounded-xl border p-5 shadow-sm transition-all"
                :class="filterWaktu === 'PAGI'
                    ? 'border-amber-400 bg-amber-50 dark:border-amber-500 dark:bg-amber-900/20'
                    : 'border-gray-200 bg-white hover:border-amber-300 dark:border-gray-700 dark:bg-gray-800'"
                @click="filterWaktu = filterWaktu === 'PAGI' ? 'SEMUA' : 'PAGI'"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-amber-600 dark:text-amber-400">Pasien Pagi</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <span v-if="isLoading" class="inline-block h-8 w-16 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                            <template v-else>{{ pagiRows.length.toLocaleString('id-ID') }}</template>
                        </p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-500 dark:bg-amber-900/30 dark:text-amber-400">
                        <Sun :size="22" />
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">Klik untuk filter pagi</p>
            </div>

            <!-- Sore -->
            <div
                class="cursor-pointer rounded-xl border p-5 shadow-sm transition-all"
                :class="filterWaktu === 'SORE'
                    ? 'border-indigo-400 bg-indigo-50 dark:border-indigo-500 dark:bg-indigo-900/20'
                    : 'border-gray-200 bg-white hover:border-indigo-300 dark:border-gray-700 dark:bg-gray-800'"
                @click="filterWaktu = filterWaktu === 'SORE' ? 'SEMUA' : 'SORE'"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Pasien Sore</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <span v-if="isLoading" class="inline-block h-8 w-16 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                            <template v-else>{{ soreRows.length.toLocaleString('id-ID') }}</template>
                        </p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-500 dark:bg-indigo-900/30 dark:text-indigo-400">
                        <Moon :size="22" />
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">Klik untuk filter sore</p>
            </div>
        </div>

        <!-- Filter aktif badge -->
        <div v-if="filterWaktu !== 'SEMUA'" class="flex items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">Filter aktif:</span>
            <span
                class="inline-flex items-center gap-1 rounded-full px-3 py-0.5 text-xs font-semibold"
                :class="filterWaktu === 'PAGI'
                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                    : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'"
            >
                <Sun v-if="filterWaktu === 'PAGI'" :size="11" />
                <Moon v-else :size="11" />
                {{ filterWaktu }}
            </span>
            <button
                class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 underline"
                @click="filterWaktu = 'SEMUA'"
            >
                Reset
            </button>
            <span class="text-xs text-gray-400">({{ filteredRows.length }} pasien)</span>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <!-- Skeleton loading -->
            <div v-if="isLoading" class="p-6 space-y-3">
                <div class="h-4 w-1/3 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                <div v-for="i in 8" :key="i" class="h-9 animate-pulse rounded bg-gray-100 dark:bg-gray-700/60" />
            </div>

            <template v-else>
                <div class="overflow-auto max-h-[55svh]">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="w-10 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Pasien</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No. RM</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Poli</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Dokter</th>
                                <th class="w-24 px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="!filteredRows.length">
                                <td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                    {{ error ? 'Tidak ada data — koneksi ke database TARAKAN gagal.' : 'Belum ada kunjungan hari ini.' }}
                                </td>
                            </tr>
                            <tr
                                v-for="(row, idx) in paginated"
                                :key="row.RegistrationNo"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30"
                            >
                                <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                    {{ (currentPage - 1) * PAGE_SIZE + idx + 1 }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ row.NamaPasien }}</p>
                                    <p class="text-xs text-gray-400">{{ row.RegistrationTime }}</p>
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs text-gray-600 dark:text-gray-400">{{ row.NoRekamMedis }}</td>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ row.NamaPoli }}</td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ row.NamaDokter }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="row.KeteranganWaktu === 'PAGI'
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                            : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'"
                                    >
                                        <Sun v-if="row.KeteranganWaktu === 'PAGI'" :size="10" />
                                        <Moon v-else :size="10" />
                                        {{ row.KeteranganWaktu }}
                                    </span>
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
                        Hal {{ currentPage }} / {{ totalPages }} &nbsp;·&nbsp; {{ filteredRows.length }} pasien
                    </span>
                    <div class="flex items-center gap-1">
                        <button
                            :disabled="currentPage === 1"
                            class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                            @click="prevPage"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
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
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>

    </div>
</template>
