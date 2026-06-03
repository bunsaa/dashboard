<script setup lang="ts">
import { Deferred, Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { AlertCircle, ChevronLeft, ChevronRight, ClipboardCheck, RotateCcw, Search, Users, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import monitoringRoutes from '@/routes/monitoring';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Monitoring',
                href: '#',
            },
            {
                title: 'Beda Kelas Peserta',
                href: props.currentTeam ? monitoringRoutes.bedaKelasPeserta(props.currentTeam.slug).url : '/',
            },
        ],
    }),
});

const props = defineProps<{
    rekap?: Array<{ tanggal: string; jumlah: number }> | null;
    cards?: {
        sudah_registrasi: number;
        rutin_kunjungan: Array<{ MedicalNo: string; NamaPasien: string }>;
    } | null;
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);

usePoll(1_800_000);

// ── Card 1: beda kelas hari ini (derived from rekap) ────────────────────────
const todayIso = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();
const bedaKelasHariIni = computed(() => props.rekap?.find(r => r.tanggal === todayIso)?.jumlah ?? 0);

// ── Card 3: sticky hover tooltip ────────────────────────────────────────────
const tooltipVisible = ref(false);
let hideTimer: ReturnType<typeof setTimeout> | null = null;

function showTooltip() {
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
    tooltipVisible.value = true;
}
function startHideTooltip() {
    hideTimer = setTimeout(() => { tooltipVisible.value = false; hideTimer = null; }, 250);
}
onBeforeUnmount(() => { if (hideTimer) clearTimeout(hideTimer); });

// ── Pagination tabel utama ──────────────────────────────────────────────────
const PAGE_SIZE = 10;
const currentPage = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil((props.rekap?.length ?? 0) / PAGE_SIZE)));
const paginated = computed(() =>
    (props.rekap ?? []).slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE),
);
function prevPage() { if (currentPage.value > 1) currentPage.value--; }
function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++; }

// ── Modal ───────────────────────────────────────────────────────────────────
type DetailRow = {
    No: number;
    MedicalNo: string;
    NoKartu: string;
    NamaPasien: string;
    AppointmentNo: string;
    RegistrationNo: string | null;
    TanggalKunjungan: string;
    NamaPoli: string;
    KelasRekamMedis: string;
    KelasBpjs: string;
};

const modalOpen = ref(false);
const modalTanggal = ref('');
const modalRows = ref<DetailRow[]>([]);
const modalLoading = ref(false);
const modalError = ref<string | null>(null);
const modalSearch = ref('');

// ── Filtered rows (case-insensitive, all columns) ────────────────────────────
const modalFiltered = computed(() => {
    const q = modalSearch.value.trim().toLowerCase();
    if (!q) return modalRows.value;
    return modalRows.value.filter(r =>
        r.MedicalNo.toLowerCase().includes(q) ||
        r.NoKartu.toLowerCase().includes(q) ||
        r.NamaPasien.toLowerCase().includes(q) ||
        r.AppointmentNo.toLowerCase().includes(q) ||
        (r.RegistrationNo ?? '').toLowerCase().includes(q) ||
        r.TanggalKunjungan.toLowerCase().includes(q) ||
        r.NamaPoli.toLowerCase().includes(q) ||
        r.KelasRekamMedis.toLowerCase().includes(q) ||
        r.KelasBpjs.toLowerCase().includes(q),
    );
});

// ── Pagination modal ────────────────────────────────────────────────────────
const modalPage = ref(1);
const modalTotalPages = computed(() => Math.max(1, Math.ceil(modalFiltered.value.length / PAGE_SIZE)));
const modalPaginated = computed(() =>
    modalFiltered.value.slice((modalPage.value - 1) * PAGE_SIZE, modalPage.value * PAGE_SIZE),
);
function modalPrevPage() { if (modalPage.value > 1) modalPage.value--; }
function modalNextPage() { if (modalPage.value < modalTotalPages.value) modalPage.value++; }

watch(modalRows, () => { modalPage.value = 1; modalSearch.value = ''; });
watch(modalSearch, () => { modalPage.value = 1; });

function formatTanggal(iso: string) {
    const d = new Date(iso);
    return d.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

async function openModal(tanggal: string) {
    if (!currentTeam.value) return;
    modalTanggal.value = tanggal;
    modalRows.value = [];
    modalError.value = null;
    modalLoading.value = true;
    modalOpen.value = true;

    try {
        const url = monitoringRoutes.bedaKelasPeserta.detail(
            { current_team: currentTeam.value.slug, tanggal },
        ).url;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => null);
        if (!res.ok) throw new Error(data?.error ?? `HTTP ${res.status}`);
        if (data?.error) throw new Error(data.error);
        modalRows.value = data;
    } catch (e: any) {
        modalError.value = e.message ?? 'Terjadi kesalahan.';
    } finally {
        modalLoading.value = false;
    }
}

function closeModal() {
    modalOpen.value = false;
}
</script>

<template>
    <Head title="Beda Kelas Peserta" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 xl:p-6">

        <!-- Header -->
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Monitoring Beda Kelas Peserta</h1>
            
        </div>

        <Deferred :data="['rekap', 'cards']">
            <!-- ── Skeleton while loading ── -->
            <template #fallback>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div
                        v-for="i in 3"
                        :key="i"
                        class="animate-pulse rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-start justify-between">
                            <div class="space-y-2">
                                <div class="h-4 w-32 rounded bg-gray-200 dark:bg-gray-700" />
                                <div class="h-9 w-20 rounded bg-gray-200 dark:bg-gray-700" />
                            </div>
                            <div class="h-10 w-10 rounded-lg bg-gray-200 dark:bg-gray-700" />
                        </div>
                        <div class="mt-4 h-3 w-48 rounded bg-gray-200 dark:bg-gray-700" />
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="overflow-auto max-h-[60svh]">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="w-12 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tanggal</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Jumlah Berbeda</th>
                                    <th class="w-28 px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="animate-pulse divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="i in 5" :key="i">
                                    <td class="px-4 py-3"><div class="h-4 w-6 rounded bg-gray-200 dark:bg-gray-700" /></td>
                                    <td class="px-4 py-3"><div class="h-4 w-48 rounded bg-gray-200 dark:bg-gray-700" /></td>
                                    <td class="px-4 py-3 text-right"><div class="ml-auto h-5 w-20 rounded-full bg-gray-200 dark:bg-gray-700" /></td>
                                    <td class="px-4 py-3 text-center"><div class="mx-auto h-6 w-14 rounded-lg bg-gray-200 dark:bg-gray-700" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <!-- ── Error rescue slot ── -->
            <template #rescue="{ reloading }">
                <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                    <AlertCircle :size="18" class="mt-0.5 shrink-0" />
                    <div class="flex-1">
                        <p class="font-medium">Tidak dapat memuat data dari database TARAKAN.</p>
                        <button
                            class="mt-2 rounded-lg border border-red-300 px-3 py-1 text-xs font-medium transition-colors hover:bg-red-100 disabled:opacity-50 dark:border-red-700 dark:hover:bg-red-900/40"
                            :disabled="reloading"
                            @click="router.reload({ only: ['rekap', 'cards'] })"
                        >
                            {{ reloading ? 'Memuat...' : 'Coba lagi' }}
                        </button>
                    </div>
                </div>
            </template>

            <!-- ── Actual content (rekap & cards are guaranteed non-null here) ── -->

            <!-- Cards ringkasan hari ini -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

                <!-- Card 1: Total beda kelas hari ini -->
                <div class="rounded-xl border border-orange-100 bg-white p-5 shadow-sm dark:border-orange-900/30 dark:bg-gray-800">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Beda Kelas Hari Ini</p>
                            <p class="mt-1 text-3xl font-bold text-orange-500 dark:text-orange-400">
                                {{ bedaKelasHariIni.toLocaleString('id-ID') }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-orange-50 p-2.5 dark:bg-orange-900/20">
                            <Users :size="22" class="text-orange-500" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Total pasien dengan perbedaan kelas BPJS hari ini</p>
                </div>

                <!-- Card 2: Sudah registrasi -->
                <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm dark:border-emerald-900/30 dark:bg-gray-800">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Sudah Registrasi</p>
                            <p class="mt-1 text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                                {{ cards!.sudah_registrasi.toLocaleString('id-ID') }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-emerald-50 p-2.5 dark:bg-emerald-900/20">
                            <ClipboardCheck :size="22" class="text-emerald-500" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Beda kelas hari ini yang sudah terdaftar registrasi</p>
                </div>

                <!-- Card 3: Rutin kunjungan (dengan hover sticky) -->
                <div
                    class="relative rounded-xl border border-blue-100 bg-white p-5 shadow-sm dark:border-blue-900/30 dark:bg-gray-800"
                    @mouseenter="showTooltip"
                    @mouseleave="startHideTooltip"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Rutin Kunjungan</p>
                            <p class="mt-1 text-3xl font-bold text-blue-600 dark:text-blue-400">
                                {{ cards!.rutin_kunjungan.length.toLocaleString('id-ID') }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-blue-50 p-2.5 dark:bg-blue-900/20">
                            <RotateCcw :size="22" class="text-blue-500" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                        Beda kelas hari ini yang rutin kunjungan <span class="font-medium">(≥2× / 7 hari)</span>
                        <span v-if="cards!.rutin_kunjungan.length" class="ml-1 text-blue-400">— Lihat daftar</span>
                    </p>

                    <!-- Tooltip daftar pasien -->
                    <div
                        v-if="tooltipVisible && cards!.rutin_kunjungan.length"
                        class="absolute left-0 top-full z-50 mt-1 w-full min-w-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
                        @mouseenter="showTooltip"
                        @mouseleave="startHideTooltip"
                    >
                        <div class="border-b border-gray-100 px-3 py-2 dark:border-gray-800">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                Daftar Pasien Rutin Kunjungan ({{ cards!.rutin_kunjungan.length }})
                            </p>
                        </div>
                        <ul class="max-h-56 overflow-y-auto py-1">
                            <li
                                v-for="p in cards!.rutin_kunjungan"
                                :key="p.MedicalNo"
                                class="flex items-baseline gap-3 px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800"
                            >
                                <span class="shrink-0 font-mono text-xs text-gray-400 dark:text-gray-500">{{ p.MedicalNo }}</span>
                                <span class="truncate text-sm text-gray-700 dark:text-gray-300">{{ p.NamaPasien }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>

            <!-- Tabel rekap -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-auto max-h-[60svh]">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="w-12 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tanggal</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Jumlah Berbeda</th>
                                <th class="w-28 px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="!rekap!.length">
                                <td colspan="4" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                    Tidak ada perbedaan kelas peserta untuk H+1 dan H+2.
                                </td>
                            </tr>
                            <tr
                                v-for="(row, idx) in paginated"
                                :key="row.tanggal"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30"
                            >
                                <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                    {{ (currentPage - 1) * PAGE_SIZE + idx + 1 }}
                                </td>
                                <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                    {{ formatTanggal(row.tanggal) }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        {{ row.jumlah.toLocaleString('id-ID') }} pasien
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <button
                                        class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                                        @click="openModal(row.tanggal)"
                                    >
                                        Lihat
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination tabel utama -->
                <div
                    v-if="totalPages > 1"
                    class="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700"
                >
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Hal {{ currentPage }} / {{ totalPages }} &nbsp;·&nbsp; {{ rekap!.length }} tanggal
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
                            >{{ p }}</button>
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
        </Deferred>
    </div>

    <!-- Modal -->
    <Teleport to="body">
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="closeModal"
        >
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-900">

                <!-- Modal header -->
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Detail Beda Kelas Peserta</h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ formatTanggal(modalTanggal) }}</p>
                        </div>
                        <button
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            @click="closeModal"
                        >
                            <X :size="18" />
                        </button>
                    </div>
                    <!-- Search -->
                    <div class="relative mt-3 max-w-sm">
                        <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            v-model="modalSearch"
                            type="text"
                            placeholder="Cari semua kolom..."
                            class="w-full rounded-lg border border-gray-200 bg-white py-1.5 pl-8 pr-8 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
                        />
                        <button
                            v-if="modalSearch"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            @click="modalSearch = ''"
                        >
                            <X :size="14" />
                        </button>
                    </div>
                </div>

                <!-- Modal body -->
                <div class="flex-1 overflow-auto">

                    <!-- Loading -->
                    <div v-if="modalLoading" class="flex items-center justify-center py-16">
                        <svg class="h-8 w-8 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                        </svg>
                    </div>

                    <!-- Error -->
                    <div v-else-if="modalError" class="m-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <AlertCircle :size="16" class="mt-0.5 shrink-0" />
                        <p>{{ modalError }}</p>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="!modalFiltered.length" class="py-16 text-center text-sm text-gray-400">
                        <template v-if="modalSearch">Tidak ada hasil untuk "<strong>{{ modalSearch }}</strong>".</template>
                        <template v-else>Tidak ada data.</template>
                    </div>

                    <!-- Table -->
                    <table v-else-if="modalFiltered.length" class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="w-10 px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                                <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No RM</th>
                                <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No Kartu</th>
                                <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Pasien</th>
                                <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No Appointment</th>
                                <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No Registrasi</th>
                                <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tgl Kunjungan</th>
                                <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Poli</th>
                                <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Kelas RM</th>
                                <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Kelas BPJS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="(row, idx) in modalPaginated"
                                :key="row.AppointmentNo"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                            >
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ (modalPage - 1) * PAGE_SIZE + idx + 1 }}
                                </td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ row.MedicalNo }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ row.NoKartu }}</td>
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ row.NamaPasien }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ row.AppointmentNo }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ row.RegistrationNo ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ row.TanggalKunjungan }}</td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ row.NamaPoli }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ row.KelasRekamMedis }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                        {{ row.KelasBpjs }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Modal footer: info + pagination -->
                <div class="border-t border-gray-200 px-6 py-3 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-400">
                            <template v-if="modalSearch">{{ modalFiltered.length.toLocaleString('id-ID') }} dari {{ modalRows.length.toLocaleString('id-ID') }} pasien</template>
                            <template v-else>Total: {{ modalRows.length.toLocaleString('id-ID') }} pasien berbeda kelas</template>
                        </p>
                        <!-- Pagination modal -->
                        <div v-if="modalTotalPages > 1" class="flex items-center gap-1">
                            <span class="mr-2 text-xs text-gray-500 dark:text-gray-400">
                                Hal {{ modalPage }} / {{ modalTotalPages }}
                            </span>
                            <button
                                :disabled="modalPage === 1"
                                class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                                @click="modalPrevPage"
                            >
                                <ChevronLeft :size="14" />
                            </button>
                            <template v-for="p in modalTotalPages" :key="p">
                                <button
                                    v-if="p === 1 || p === modalTotalPages || Math.abs(p - modalPage) <= 1"
                                    class="inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded border px-1 text-xs transition-colors"
                                    :class="p === modalPage
                                        ? 'border-emerald-500 bg-emerald-500 text-white'
                                        : 'border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700'"
                                    @click="modalPage = p"
                                >{{ p }}</button>
                                <span v-else-if="p === 2 && modalPage > 3" class="px-0.5 text-xs text-gray-400">…</span>
                                <span v-else-if="p === modalTotalPages - 1 && modalPage < modalTotalPages - 2" class="px-0.5 text-xs text-gray-400">…</span>
                            </template>
                            <button
                                :disabled="modalPage === modalTotalPages"
                                class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                                @click="modalNextPage"
                            >
                                <ChevronRight :size="14" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
