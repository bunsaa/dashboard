<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { AlertCircle, AlertOctagonIcon, BadgeCheck, BadgeDollarSign, Calendar, ChevronLeft, ChevronRight, FileText, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import monitoringRoutes from '@/routes/monitoring';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            { title: 'Monitoring', href: '#' },
            {
                title: 'Klaim BPJS',
                href: props.currentTeam ? monitoringRoutes.klaimBpjs(props.currentTeam.slug).url : '/',
            },
        ],
    }),
});

const props = defineProps<{
    bulan: string;
    q: string;
    cards: {
        total_klaim: number;
        total_diajukan: number;
        total_disetujui: number;
        selisih: number;
        selisih_count: number;
    };
    items: Array<{
        No: number;
        SepNo: string;
        NoRM: string;
        NamaPasien: string;
        TanggalVerifikasi: string;
        BiayaDiajukan: number;
        BiayaDisetujui: number;
        Selisih: number;
        NoBAHV: string | null;
    }>;
    selisih_detail: Array<{
        SepNo: string;
        NoRM: string;
        NamaPasien: string;
        Selisih: number;
    }>;
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    error: string | null;
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);

usePoll(1_800_000);

// ── Month display ────────────────────────────────────────────────────────────
const bulanLabel = computed(() => {
    const [y, m] = props.bulan.split('-').map(Number);
    return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(new Date(y, m - 1, 1));
});

const currentMonthValue = `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`;
const isCurrentMonth = computed(() => props.bulan === currentMonthValue);

// ── Month picker ─────────────────────────────────────────────────────────────
function onMonthChange(e: Event) {
    const val = (e.target as HTMLInputElement).value;
    if (val) navigate(val);
}

// ── Month navigation ─────────────────────────────────────────────────────────
function navigate(bulan: string, pg = 1) {
    if (!currentTeam.value) return;
    router.get(monitoringRoutes.klaimBpjs(currentTeam.value.slug).url, { bulan, page: pg });
}

function prevMonth() {
    const [y, m] = props.bulan.split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    navigate(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
}

function nextMonth() {
    const [y, m] = props.bulan.split('-').map(Number);
    const d = new Date(y, m, 1);
    navigate(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
}

function goToCurrentMonth() {
    navigate(currentMonthValue);
}

// ── Search ───────────────────────────────────────────────────────────────────
const searchInput = ref(props.q ?? '');
let searchDebounce: ReturnType<typeof setTimeout> | null = null;
let syncing = false;

watch(() => props.q, (val) => {
    if ((val ?? '') !== searchInput.value) {
        syncing = true;
        searchInput.value = val ?? '';
        // Reset flag after this tick's watchers run
        setTimeout(() => { syncing = false; }, 0);
    }
});

watch(searchInput, (val) => {
    if (syncing) return;
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        searchDebounce = null;
        if (!currentTeam.value) return;
        router.get(
            monitoringRoutes.klaimBpjs(currentTeam.value.slug).url,
            { bulan: props.bulan, page: 1, ...(val ? { q: val } : {}) },
            { only: ['items', 'pagination'] },
        );
    }, 400);
});

// ── Pagination ───────────────────────────────────────────────────────────────
function goToPage(pg: number) {
    if (!currentTeam.value) return;
    router.get(
        monitoringRoutes.klaimBpjs(currentTeam.value.slug).url,
        { bulan: props.bulan, page: pg, ...(searchInput.value ? { q: searchInput.value } : {}) },
        { only: ['items', 'pagination'] },
    );
}

const pageNumbers = computed((): (number | '...')[] => {
    const total = props.pagination.last_page;
    const cur = props.pagination.current_page;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    const pages: (number | '...')[] = [1];
    if (cur > 3) pages.push('...');
    for (let p = Math.max(2, cur - 1); p <= Math.min(total - 1, cur + 1); p++) {
        pages.push(p);
    }
    if (cur < total - 2) pages.push('...');
    pages.push(total);
    return pages;
});

// ── Selisih card tooltip (sticky hover) ─────────────────────────────────────
const selisihTooltipVisible = ref(false);
let hideSelisihTimer: ReturnType<typeof setTimeout> | null = null;

function showSelisihTooltip() {
    if (hideSelisihTimer) { clearTimeout(hideSelisihTimer); hideSelisihTimer = null; }
    selisihTooltipVisible.value = true;
}
function startHideSelisihTooltip() {
    hideSelisihTimer = setTimeout(() => { selisihTooltipVisible.value = false; hideSelisihTimer = null; }, 250);
}

onBeforeUnmount(() => {
    if (searchDebounce) clearTimeout(searchDebounce);
    if (hideSelisihTimer) clearTimeout(hideSelisihTimer);
});

// ── Currency formatting ──────────────────────────────────────────────────────
function formatRupiahKompak(value: number): string {
    const abs = Math.abs(value);
    const sign = value < 0 ? '-' : '';
    if (abs >= 1_000_000_000) {
        return `${sign}Rp ${(abs / 1_000_000_000).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} M`;
    }
    if (abs >= 1_000_000) {
        return `${sign}Rp ${(abs / 1_000_000).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Jt`;
    }
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}

function formatRupiah(value: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}
</script>

<template>
    <Head title="Klaim BPJS" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 xl:p-6">

        <!-- Header + month navigator -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Monitoring Klaim BPJS</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Data klaim BPJS berdasarkan tanggal verifikasi</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Month label — click to open native month picker -->
                <div class="relative">
                    <input
                        type="month"
                        :value="bulan"
                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                        @change="onMonthChange"
                    />
                    <span class="pointer-events-none text-sm font-semibold capitalize text-gray-900 dark:text-gray-100">
                        {{ bulanLabel }}
                    </span>
                </div>

                <!-- Grouped nav buttons: prev | bulan-berjalan | next -->
                <div class="flex divide-x divide-gray-200 overflow-hidden rounded-lg border border-gray-200 dark:divide-gray-600 dark:border-gray-600">
                    <button
                        class="inline-flex h-8 w-8 items-center justify-center text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                        @click="prevMonth"
                    >
                        <ChevronLeft :size="15" />
                    </button>

                    <!-- Go to current month -->
                    <div class="group relative">
                        <button
                            class="inline-flex h-8 w-8 items-center justify-center transition-colors"
                            :class="isCurrentMonth
                                ? 'bg-emerald-500 text-white hover:bg-emerald-600'
                                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'"
                            @click="goToCurrentMonth"
                        >
                            <Calendar :size="15" />
                        </button>
                        <!-- Tooltip -->
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 dark:bg-gray-700">
                            Bulan berjalan
                            <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-gray-800 dark:border-t-gray-700" />
                        </div>
                    </div>

                    <button
                        class="inline-flex h-8 w-8 items-center justify-center text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                        @click="nextMonth"
                    >
                        <ChevronRight :size="15" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Error alert -->
        <div
            v-if="props.error"
            class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            <AlertCircle :size="18" class="mt-0.5 shrink-0" />
            <p>{{ props.error }}</p>
        </div>

        <!-- Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

            <!-- Card 1: Total klaim -->
            <div class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm dark:border-blue-900/30 dark:bg-gray-800">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Klaim</p>
                        <p class="mt-1 text-3xl font-bold text-blue-600 dark:text-blue-400">
                            {{ cards.total_klaim.toLocaleString('id-ID') }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-blue-50 p-2.5 dark:bg-blue-900/20">
                        <FileText :size="22" class="text-blue-500" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Jumlah SEP bulan {{ bulanLabel }}</p>
            </div>

            <!-- Card 2: Total diajukan -->
            <div class="rounded-xl border border-indigo-100 bg-white p-5 shadow-sm dark:border-indigo-900/30 dark:bg-gray-800">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Diajukan</p>
                        <p class="mt-1 text-2xl font-bold leading-tight text-indigo-600 dark:text-indigo-400">
                            {{ formatRupiahKompak(cards.total_diajukan) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-2.5 dark:bg-indigo-900/20">
                        <BadgeDollarSign :size="22" class="text-indigo-500" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Total biaya yang diajukan ke BPJS</p>
            </div>

            <!-- Card 3: Total disetujui -->
            <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm dark:border-emerald-900/30 dark:bg-gray-800">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Disetujui</p>
                        <p class="mt-1 text-2xl font-bold leading-tight text-emerald-600 dark:text-emerald-400">
                            {{ formatRupiahKompak(cards.total_disetujui) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-2.5 dark:bg-emerald-900/20">
                        <BadgeCheck :size="22" class="text-emerald-500" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Total biaya yang disetujui oleh BPJS</p>
            </div>

            <!-- Card 4: Selisih (with sticky hover tooltip) -->
            <div
                class="relative rounded-xl border border-orange-100 bg-white p-5 shadow-sm dark:border-orange-900/30 dark:bg-gray-800"
                @mouseenter="showSelisihTooltip"
                @mouseleave="startHideSelisihTooltip"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Selisih</p>
                        <p
                            class="mt-1 text-2xl font-bold leading-tight"
                            :class="cards.selisih >= 0 ? 'text-orange-500 dark:text-orange-400' : 'text-red-500 dark:text-red-400'"
                        >
                            {{ formatRupiahKompak(cards.selisih) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-orange-50 p-2.5 dark:bg-orange-900/20">
                        <AlertOctagonIcon :size="22" class="text-orange-500" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                     Lihat daftar
                    
                </p>

                <!-- Tooltip daftar SEP dengan selisih -->
                <div
                    v-if="selisihTooltipVisible && selisih_detail.length"
                    class="absolute right-0 top-full z-50 mt-1 w-full min-w-96 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
                    @mouseenter="showSelisihTooltip"
                    @mouseleave="startHideSelisihTooltip"
                >
                    <div class="border-b border-gray-100 px-3 py-2 dark:border-gray-800">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                            SEP dengan Selisih — Top {{ selisih_detail.length }}
                            <span v-if="cards.selisih_count > selisih_detail.length" class="font-normal text-gray-400">
                                dari {{ cards.selisih_count.toLocaleString('id-ID') }}
                            </span>
                        </p>
                    </div>
                    <ul class="max-h-64 overflow-y-auto py-1">
                        <li
                            v-for="s in selisih_detail"
                            :key="s.SepNo"
                            class="grid grid-cols-[1fr_auto] gap-x-3 px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            <div class="min-w-0">
                                <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ s.SepNo }}</span>
                                <span class="block truncate text-xs text-gray-700 dark:text-gray-300">
                                    {{ s.NoRM ? `${s.NoRM} · ` : '' }}{{ s.NamaPasien || '—' }}
                                </span>
                            </div>
                            <span class="shrink-0 self-center text-xs font-medium text-orange-600 dark:text-orange-400">
                                {{ formatRupiahKompak(s.Selisih) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Table card -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">

            <!-- Search bar -->
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <div class="relative max-w-sm">
                    <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="Cari No. SEP, No. RM, Nama, atau Tanggal..."
                        class="w-full rounded-lg border border-gray-200 bg-white py-1.5 pl-8 pr-8 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
                    />
                    <button
                        v-if="searchInput"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        @click="searchInput = ''"
                    >
                        <X :size="14" />
                    </button>
                </div>
            </div>

            <div class="overflow-auto max-h-[60svh]">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="w-10 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No. SEP</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No. RM</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Pasien</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tgl. Verifikasi</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Biaya Diajukan</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Biaya Disetujui</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="!items.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                <template v-if="props.error">Tidak ada data — koneksi ke database gagal.</template>
                                <template v-else-if="searchInput">Tidak ada hasil untuk "<strong>{{ searchInput }}</strong>".</template>
                                <template v-else>Tidak ada data klaim untuk {{ bulanLabel }}.</template>
                            </td>
                        </tr>
                        <tr
                            v-for="row in items"
                            :key="row.SepNo"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30"
                        >
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ row.No }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-900 dark:text-gray-100">{{ row.SepNo }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-600 dark:text-gray-400">{{ row.NoRM || '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-800 dark:text-gray-200">{{ row.NamaPasien || '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ row.TanggalVerifikasi }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-900 dark:text-gray-100">{{ formatRupiah(row.BiayaDiajukan) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-900 dark:text-gray-100">{{ formatRupiah(row.BiayaDisetujui) }}</td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :class="row.Selisih === 0
                                    ? 'text-gray-400 dark:text-gray-500'
                                    : row.Selisih > 0
                                        ? 'text-orange-600 dark:text-orange-400'
                                        : 'text-red-600 dark:text-red-400'"
                            >
                                {{ row.Selisih === 0 ? '—' : formatRupiah(row.Selisih) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination.total > 0"
                class="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700"
            >
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Hal {{ pagination.current_page }} / {{ pagination.last_page }}
                    &nbsp;·&nbsp;
                    {{ pagination.total.toLocaleString('id-ID') }} klaim
                </span>
                <div class="flex items-center gap-1">
                    <button
                        :disabled="pagination.current_page === 1"
                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                        @click="goToPage(pagination.current_page - 1)"
                    >
                        <ChevronLeft :size="14" />
                    </button>
                    <template v-for="p in pageNumbers" :key="p">
                        <button
                            v-if="typeof p === 'number'"
                            class="inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded border px-1 text-xs transition-colors"
                            :class="p === pagination.current_page
                                ? 'border-blue-500 bg-blue-500 text-white'
                                : 'border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700'"
                            @click="goToPage(p)"
                        >{{ p }}</button>
                        <span v-else class="px-0.5 text-xs text-gray-400">…</span>
                    </template>
                    <button
                        :disabled="pagination.current_page === pagination.last_page"
                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                        @click="goToPage(pagination.current_page + 1)"
                    >
                        <ChevronRight :size="14" />
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
