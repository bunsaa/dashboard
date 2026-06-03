<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ChartItem = {
    index: number;
    label: string;
    total_rab: number;
    total_hps: number;
    total_nominal: number;
    persen: number;
    count_uraian: number;
    count_kontrak: number;
    coverage_persen: number;
};

type ProgressSubEntry = {
    label: string;
    tgl_mulai: string | null;
    tgl_akhir: string | null;
    rencana: number;
    realisasi: number;
    keterangan: string | null;
    not_started: boolean;
};

type ProgressTimelineEntry = {
    label: string;
    tgl_mulai: string | null;
    tgl_akhir: string | null;
    rencana: number;
    realisasi: number;
    keterangan: string | null;
    not_started: boolean;
    children: ProgressSubEntry[];
};

type TimelineItem = {
    label: string;
    tanggal_kontrak: string;
    tanggal_akhir: string;
    no_kontrak: string;
    progress: ProgressTimelineEntry[];
    persen_progress: number | null;
};

type TimelineGroup = {
    label: string;
    items: TimelineItem[];
};

type KurvaSLine = {
    label: string;
    rencana: (number | null)[];
    realisasi: (number | null)[];
};

type KurvaS = {
    labels: string[];
    lines: KurvaSLine[];
};

type InstansiOption = { id: number; nama_instansi: string };

type EditHistoryEntry = {
    tanggal: string;
    nominal_sebelumnya: number;
    diubah_oleh: string;
};

type AnggaranData = {
    id: number;
    nominal: number;
    edit_count: number;
    edit_history: EditHistoryEntry[];
    created_by: string;
} | null;

const props = defineProps<{
    chartData: ChartItem[];
    timeline: TimelineGroup[];
    kurvaS: KurvaS;
    totalAktivitas: number;
    totalKontrak: number;
    totalNilai: number;
    totalRab: number;
    anggaranData: AnggaranData;
    year: number;
    instansiList: InstansiOption[];
    selectedInstansi: number | null;
    isAdmin: boolean;
}>();

const page     = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug ?? '');
function monevRoute(path: string) { return `/${teamSlug.value}/monev${path}`; }

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '#' }];

const isAdmin = computed(() => props.isAdmin);

// ── Anggaran Pengadaan form ────────────────────────────────────────────────────

const anggaranDialogOpen  = ref(false);
const anggaranHistoryOpen = ref(false);

const anggaranForm = useForm({
    nominal:  '' as string | number,
    tahun:    props.year,
});

watch(() => props.year, (v) => { anggaranForm.tahun = v; });

function openAnggaranDialog() {
    anggaranForm.nominal = props.anggaranData?.nominal ?? '';
    anggaranForm.tahun   = props.year;
    anggaranDialogOpen.value = true;
}

function submitAnggaran() {
    anggaranForm.post(monevRoute('/anggaran'), {
        onSuccess: () => { anggaranDialogOpen.value = false; },
    });
}

// ── Year + Instansi filter ─────────────────────────────────────────────────────

const currentYear    = ref<number>(props.year);
const filterInstansi = ref<number | null>(props.selectedInstansi);

// Keep in sync when Inertia updates props (preserveState revisit)
watch(() => props.year,             (v) => { currentYear.value    = v; });
watch(() => props.selectedInstansi, (v) => { filterInstansi.value = v; });

function navigate() {
    const params: Record<string, string | number> = { year: currentYear.value };
    if (filterInstansi.value) params.instansi_id = filterInstansi.value;
    router.get(monevRoute('/dashboard'), params, { preserveState: true });
}

function prevYear() { currentYear.value--; navigate(); }
function nextYear() { currentYear.value++; navigate(); }

// ── Helpers ───────────────────────────────────────────────────────────────────

const MONTH_WIDTH = 72;

function toRoman(num: number): string {
    const map: [number, string][] = [
        [1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'],
        [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'],
        [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I'],
    ];
    let result = '';
    for (const [value, symbol] of map) {
        while (num >= value) { result += symbol; num -= value; }
    }
    return result;
}

function formatRp(value: number): string {
    if (!value) return 'Rp 0';
    if (value >= 1_000_000_000)
        return `Rp ${(value / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 2 })} M`;
    if (value >= 1_000_000)
        return `Rp ${(value / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}

// ── Chart ─────────────────────────────────────────────────────────────────────

const maxChartValue = computed(() => {
    if (!props.chartData.length) return 1;
    return Math.max(...props.chartData.flatMap((d) => [d.total_rab, d.total_hps, d.total_nominal]), 1);
});

function chartBarPct(value: number): string {
    return `${Math.min((value / maxChartValue.value) * 100, 100)}%`;
}

// ── Timeline ──────────────────────────────────────────────────────────────────

const allTimelineItems = computed(() => props.timeline.flatMap((g) => g.items));

const timelineStart = computed((): Date => {
    if (!allTimelineItems.value.length) return new Date();
    const min = Math.min(...allTimelineItems.value.map((i) => new Date(i.tanggal_kontrak).getTime()));
    const d = new Date(min);
    return new Date(d.getFullYear(), d.getMonth(), 1);
});

const timelineEnd = computed((): Date => {
    if (!allTimelineItems.value.length) return new Date();
    const max = Math.max(...allTimelineItems.value.map((i) => new Date(i.tanggal_akhir).getTime()));
    const d = new Date(max);
    return new Date(d.getFullYear(), d.getMonth() + 1, 0);
});

const totalDays = computed(() =>
    Math.max((timelineEnd.value.getTime() - timelineStart.value.getTime()) / (1000 * 60 * 60 * 24), 1),
);

const months = computed(() => {
    const result: { label: string; left: number }[] = [];
    const d = new Date(timelineStart.value.getFullYear(), timelineStart.value.getMonth(), 1);
    let idx = 0;
    while (d <= timelineEnd.value) {
        result.push({
            label: d.toLocaleDateString('id-ID', { month: 'short', year: '2-digit' }),
            left: idx * MONTH_WIDTH,
        });
        d.setMonth(d.getMonth() + 1);
        idx++;
    }
    return result;
});

const totalTimelineWidth = computed(() => months.value.length * MONTH_WIDTH);

function itemBarStyle(item: TimelineItem): Record<string, string> {
    const start = new Date(item.tanggal_kontrak).getTime();
    const end   = new Date(item.tanggal_akhir).getTime();
    const startDays = (start - timelineStart.value.getTime()) / (1000 * 60 * 60 * 24);
    const endDays   = (end   - timelineStart.value.getTime()) / (1000 * 60 * 60 * 24);
    const left  = (startDays / totalDays.value) * totalTimelineWidth.value;
    const width = Math.max(((endDays - startDays) / totalDays.value) * totalTimelineWidth.value, 8);
    return { left: `${left}px`, width: `${width}px` };
}

function progressBarStyle(entry: { tgl_mulai: string | null; tgl_akhir: string | null }): Record<string, string> {
    if (!entry.tgl_mulai || !entry.tgl_akhir) return { display: 'none' };
    const start     = new Date(entry.tgl_mulai + 'T00:00:00').getTime();
    const end       = new Date(entry.tgl_akhir + 'T00:00:00').getTime();
    const startDays = (start - timelineStart.value.getTime()) / (1000 * 60 * 60 * 24);
    const endDays   = (end   - timelineStart.value.getTime()) / (1000 * 60 * 60 * 24) + 1;
    const left  = (startDays / totalDays.value) * totalTimelineWidth.value;
    const width = Math.max(((endDays - startDays) / totalDays.value) * totalTimelineWidth.value, 12);
    return { left: `${left}px`, width: `${width}px` };
}

// ── Kurva S (SVG multi-line chart) ────────────────────────────────────────────

const SVG_W = 800;
const SVG_H = 300;
const PAD   = { top: 20, right: 20, bottom: 56, left: 48 };

const chartW = SVG_W - PAD.left - PAD.right;
const chartH = SVG_H - PAD.top  - PAD.bottom;

// Fixed colour palette per kontrak line
const LINE_COLORS = [
    '#3b82f6', '#22c55e', '#f59e0b', '#ef4444',
    '#8b5cf6', '#06b6d4', '#f97316', '#84cc16',
    '#ec4899', '#14b8a6',
];

function lineColor(i: number): string {
    return LINE_COLORS[i % LINE_COLORS.length];
}

function kurvaSX(i: number, total: number): number {
    if (total <= 1) return PAD.left;
    return PAD.left + (i / (total - 1)) * chartW;
}

function kurvaSY(val: number): number {
    return PAD.top + chartH - (val / 100) * chartH;
}

// Build polyline points, skipping null values (breaks line at gaps)
function buildSegments(vals: (number | null)[], total: number): string[] {
    const segments: string[] = [];
    let current: string[] = [];
    for (let i = 0; i < vals.length; i++) {
        if (vals[i] !== null) {
            current.push(`${kurvaSX(i, total)},${kurvaSY(vals[i]!)}`);
        } else {
            if (current.length > 1) segments.push(current.join(' '));
            current = [];
        }
    }
    if (current.length > 1) segments.push(current.join(' '));
    return segments;
}

const yTicks = [0, 20, 40, 60, 80, 100];

// Max number of x-axis labels to show (avoid crowding)
const xLabelStep = computed(() => {
    const n = props.kurvaS.labels.length;
    if (n <= 12) return 1;
    if (n <= 24) return 2;
    return 3;
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">

            <!-- ── Year Nav + Instansi Filter ── -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Year navigation -->
                <div class="flex items-center gap-1">
                    <button
                        class="flex h-8 w-8 items-center justify-center rounded-md border border-input text-sm hover:bg-muted"
                        title="Tahun sebelumnya"
                        @click="prevYear"
                    >&lsaquo;</button>
                    <span class="min-w-[4rem] text-center text-sm font-semibold tabular-nums">{{ currentYear }}</span>
                    <button
                        class="flex h-8 w-8 items-center justify-center rounded-md border border-input text-sm hover:bg-muted"
                        title="Tahun berikutnya"
                        @click="nextYear"
                    >&rsaquo;</button>
                </div>

                <!-- Instansi filter (admin only) -->
                <template v-if="isAdmin">
                    <select
                        v-model="filterInstansi"
                        class="h-8 rounded-md border border-input bg-background px-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        @change="navigate"
                    >
                        <option :value="null">Semua instansi</option>
                        <option v-for="ins in instansiList" :key="ins.id" :value="ins.id">
                            {{ ins.nama_instansi }}
                        </option>
                    </select>
                    <button
                        v-if="filterInstansi"
                        class="h-8 rounded-md border border-input px-2 text-xs text-muted-foreground hover:bg-muted"
                        @click="filterInstansi = null; navigate()"
                    >Reset</button>
                </template>
            </div>

            <!-- ── Stats Cards ── -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border p-5">
                    <p class="text-sm font-medium text-muted-foreground">Total Jenis Kegiatan</p>
                    <p class="mt-1 text-3xl font-bold">{{ totalAktivitas }}</p>
                </div>
                <div class="rounded-xl border p-5">
                    <p class="text-sm font-medium text-muted-foreground">Total Kontrak</p>
                    <p class="mt-1 text-3xl font-bold">{{ totalKontrak }}</p>
                </div>
                <div class="rounded-xl border p-5">
                    <p class="text-sm font-medium text-muted-foreground">Total Nilai Kontrak</p>
                    <p class="mt-1 text-2xl font-bold">{{ formatRp(totalNilai) }}</p>
                </div>
            </div>

            <!-- ── Extra Cards Row ── -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

                <!-- Card: Total RAB -->
                <div class="rounded-xl border p-5">
                    <p class="text-sm font-medium text-muted-foreground">Total Anggaran RAB</p>
                    <p class="mt-1 text-2xl font-bold">{{ formatRp(totalRab) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Penjumlahan RAB seluruh uraian kegiatan</p>
                </div>

                <!-- Card: Total Realisasi Kontrak -->
                <div class="rounded-xl border p-5">
                    <p class="text-sm font-medium text-muted-foreground">Total Realisasi Kontrak</p>
                    <p class="mt-1 text-2xl font-bold">{{ formatRp(totalNilai) }}</p>
                    <p v-if="totalRab > 0" class="mt-1 text-xs"
                        :class="totalNilai / totalRab >= 0.9 ? 'text-green-600 dark:text-green-400' : totalNilai / totalRab >= 0.5 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-500'"
                    >{{ ((totalNilai / totalRab) * 100).toFixed(1) }}% dari RAB</p>
                </div>

                <!-- Card: Anggaran Pengadaan -->
                <div class="rounded-xl border p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-medium text-muted-foreground">Anggaran Pengadaan {{ year }}</p>
                        <div class="flex shrink-0 items-center gap-1">
                            <!-- Edit history tooltip -->
                            <div v-if="anggaranData && anggaranData.edit_history.length" class="relative">
                                <button
                                    class="flex size-6 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
                                    title="Riwayat edit"
                                    @click="anggaranHistoryOpen = !anggaranHistoryOpen"
                                >
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3z" />
                                    </svg>
                                </button>
                                <div v-if="anggaranHistoryOpen"
                                    class="absolute right-0 top-7 z-10 w-64 rounded-lg border bg-popover p-3 shadow-md text-xs"
                                >
                                    <p class="mb-2 font-semibold">Riwayat Edit</p>
                                    <div v-for="(h, i) in anggaranData.edit_history" :key="i" class="mb-1.5 border-b pb-1.5 last:border-0 last:mb-0 last:pb-0">
                                        <p class="text-muted-foreground">{{ h.tanggal }}</p>
                                        <p>Nominal sebelumnya: <span class="font-medium">{{ formatRp(h.nominal_sebelumnya) }}</span></p>
                                        <p class="text-muted-foreground">oleh {{ h.diubah_oleh }}</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Input/Edit button -->
                            <button
                                v-if="!anggaranData || anggaranData.edit_count < 2"
                                class="flex size-6 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
                                :title="anggaranData ? 'Edit anggaran' : 'Input anggaran'"
                                @click="openAnggaranDialog"
                            >
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p v-if="anggaranData" class="mt-1 text-2xl font-bold">{{ formatRp(anggaranData.nominal) }}</p>
                    <p v-else class="mt-2 text-sm text-muted-foreground italic">Belum diinput</p>
                    <p v-if="anggaranData" class="mt-1 text-xs text-muted-foreground">
                        Sisa edit: {{ 2 - anggaranData.edit_count }}x
                    </p>
                </div>
            </div>

            <!-- Anggaran Pengadaan Dialog -->
            <Dialog v-model:open="anggaranDialogOpen">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>{{ anggaranData ? 'Edit' : 'Input' }} Anggaran Pengadaan {{ year }}</DialogTitle>
                    </DialogHeader>
                    <form @submit.prevent="submitAnggaran" class="flex flex-col gap-4 pt-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Nominal Anggaran</label>
                            <input
                                v-model="anggaranForm.nominal"
                                type="number"
                                min="0"
                                step="1"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                placeholder="Contoh: 500000000"
                                required
                            />
                            <p v-if="anggaranForm.errors.nominal" class="mt-1 text-xs text-destructive">{{ anggaranForm.errors.nominal }}</p>
                            <p v-if="anggaranData" class="mt-1 text-xs text-muted-foreground">
                                Sisa edit: {{ 2 - anggaranData.edit_count }}x. Tidak dapat dihapus.
                            </p>
                        </div>
                        <DialogFooter>
                            <button type="button" class="rounded-md border px-4 py-2 text-sm hover:bg-muted" @click="anggaranDialogOpen = false">Batal</button>
                            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90" :disabled="anggaranForm.processing">Simpan</button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- ── Chart per Jenis Kegiatan ── -->
            <div class="rounded-xl border">
                <div class="border-b px-4 py-3">
                    <h2 class="font-semibold">Realisasi per Jenis Kegiatan</h2>
                    <p class="text-xs text-muted-foreground">Perbandingan RAB, HPS, dan Nilai Kontrak</p>
                </div>

                <div class="divide-y">
                    <div v-for="(item, idx) in chartData" :key="idx" class="px-4 py-4">
                        <div class="mb-2.5 flex items-center justify-between">
                            <p class="text-sm font-semibold">
                                {{ toRoman(item.index) }}. {{ item.label }}
                            </p>
                            <span class="shrink-0 text-xs text-muted-foreground">
                                {{ item.count_uraian }} uraian &middot; {{ item.count_kontrak }} kontrak
                            </span>
                        </div>

                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-3">
                                <span class="w-14 shrink-0 text-xs text-muted-foreground">RAB</span>
                                <div class="h-3 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-blue-400 dark:bg-blue-600" :style="{ width: chartBarPct(item.total_rab) }" />
                                </div>
                                <span class="w-32 shrink-0 text-right text-xs tabular-nums">{{ formatRp(item.total_rab) }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-14 shrink-0 text-xs text-muted-foreground">HPS</span>
                                <div class="h-3 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-indigo-400 dark:bg-indigo-600" :style="{ width: chartBarPct(item.total_hps) }" />
                                </div>
                                <span class="w-32 shrink-0 text-right text-xs tabular-nums">{{ formatRp(item.total_hps) }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-14 shrink-0 text-xs text-muted-foreground">Kontrak</span>
                                <div class="h-3 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full"
                                        :class="
                                            item.coverage_persen >= 90 ? 'bg-green-500' :
                                            item.coverage_persen >= 50 ? 'bg-yellow-500' : 'bg-red-400'
                                        "
                                        :style="{ width: `${Math.min(item.coverage_persen, 100)}%` }"
                                    />
                                </div>
                                <span class="w-32 shrink-0 text-right text-xs tabular-nums">
                                    {{ item.count_kontrak }}/{{ item.count_uraian }} uraian
                                    <span
                                        class="ml-1 font-semibold"
                                        :class="
                                            item.coverage_persen >= 90 ? 'text-green-600 dark:text-green-400' :
                                            item.coverage_persen >= 50 ? 'text-yellow-600 dark:text-yellow-400' :
                                            'text-red-500 dark:text-red-400'
                                        "
                                    >{{ item.coverage_persen }}%</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!chartData.length" class="p-8 text-center text-sm text-muted-foreground">
                        Belum ada data jenis kegiatan.
                    </div>
                </div>
            </div>

            <!-- ── Timeline Kontrak (Gantt) ── -->
            <div class="rounded-xl border">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <div>
                        <h2 class="font-semibold">Timeline Kontrak</h2>
                        <p class="text-xs text-muted-foreground">Dari tanggal kontrak hingga tanggal selesai</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block h-3 w-5 rounded-sm bg-blue-300 dark:bg-blue-700" />
                            Kontrak
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block h-2.5 w-5 rounded-sm bg-green-400" />
                            Progress
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block h-2 w-5 rounded-sm bg-sky-300" />
                            Sub-uraian
                        </span>
                    </div>
                </div>

                <div v-if="timeline.length" class="overflow-x-auto">
                    <div :style="{ minWidth: `${208 + totalTimelineWidth}px` }">

                        <!-- Month header -->
                        <div class="flex border-b bg-muted/30">
                            <div class="flex w-52 shrink-0 items-center border-r px-3 py-1.5 text-xs font-medium text-muted-foreground">
                                Uraian Kegiatan
                            </div>
                            <div class="relative h-8" :style="{ width: totalTimelineWidth + 'px' }">
                                <div
                                    v-for="(m, mi) in months"
                                    :key="mi"
                                    class="absolute flex h-full items-center border-r px-1 text-[10px] text-muted-foreground"
                                    :style="{ left: m.left + 'px', width: MONTH_WIDTH + 'px' }"
                                >
                                    {{ m.label }}
                                </div>
                            </div>
                        </div>

                        <!-- Groups + rows -->
                        <template v-for="(group, gi) in timeline" :key="gi">
                            <!-- Group header -->
                            <div class="flex items-center border-b bg-muted/50">
                                <div class="w-52 shrink-0 border-r px-3 py-1.5 text-xs font-semibold">
                                    {{ toRoman(gi + 1) }}. {{ group.label }}
                                </div>
                                <div :style="{ width: totalTimelineWidth + 'px' }" />
                            </div>

                            <!-- Item rows -->
                            <template v-for="(item, ii) in group.items" :key="ii">

                                <!-- ── Baris kontrak (Gantt bar) ── -->
                                <div class="flex items-center border-b border-border/40">
                                    <div class="w-52 shrink-0 border-r px-3 py-1.5 text-xs text-muted-foreground">
                                        <div class="flex min-w-0 items-center justify-between gap-1">
                                            <span class="min-w-0 truncate">
                                                <span class="mr-1 font-medium">{{ ii + 1 }}.</span>{{ item.label }}
                                            </span>
                                            <span
                                                v-if="item.persen_progress !== null"
                                                class="shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-bold"
                                                :class="
                                                    item.persen_progress >= 100 ? 'bg-green-100 text-green-700 dark:bg-green-900/60 dark:text-green-300' :
                                                    item.persen_progress >= 80  ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/60 dark:text-yellow-300' :
                                                                                  'bg-red-100 text-red-600 dark:bg-red-900/60 dark:text-red-400'
                                                "
                                                :title="`Rata-rata progress: ${item.persen_progress}%`"
                                            >{{ item.persen_progress }}%</span>
                                        </div>
                                    </div>
                                    <div class="relative" :style="{ width: totalTimelineWidth + 'px', height: '36px' }">
                                        <div
                                            v-for="(m, mi) in months" :key="mi"
                                            class="absolute inset-y-0 w-px bg-border/40"
                                            :style="{ left: m.left + 'px' }"
                                        />
                                        <div
                                            class="absolute inset-y-2 flex items-center overflow-hidden rounded bg-blue-200 px-1.5 text-[10px] leading-none text-blue-900 dark:bg-blue-800/60 dark:text-blue-100"
                                            :style="itemBarStyle(item)"
                                            :title="`${item.no_kontrak}\n${item.tanggal_kontrak} s/d ${item.tanggal_akhir}`"
                                        >
                                            <span class="truncate">{{ item.no_kontrak }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- ── Progress sub-rows ── -->
                                <template v-for="(prog, pi) in item.progress" :key="`prog-${ii}-${pi}`">

                                    <!-- Progress main entry row -->
                                    <div class="flex items-center border-b border-border/20 bg-muted/10">
                                        <div class="flex w-52 shrink-0 flex-col justify-center gap-0.5 border-r py-1 pl-7 pr-3 text-[10px] text-muted-foreground">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="inline-block h-1.5 w-1.5 shrink-0 rounded-full"
                                                    :class="prog.not_started ? 'bg-gray-400' : 'bg-green-500'"
                                                />
                                                <span class="min-w-0 truncate font-medium">{{ prog.label || 'Progress' }}</span>
                                                <span
                                                    v-if="prog.not_started"
                                                    class="ml-auto shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-[9px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                                                >Not Started</span>
                                                <span
                                                    v-else
                                                    class="ml-auto shrink-0 font-semibold"
                                                    :class="
                                                        prog.realisasi >= prog.rencana
                                                            ? 'text-green-600 dark:text-green-400'
                                                            : prog.realisasi >= prog.rencana * 0.8
                                                                ? 'text-yellow-600 dark:text-yellow-400'
                                                                : 'text-red-500 dark:text-red-400'
                                                    "
                                                >{{ prog.realisasi }}%</span>
                                            </div>
                                            <p
                                                v-if="prog.keterangan"
                                                class="truncate pl-3 text-[10px] italic text-muted-foreground/60"
                                                :title="prog.keterangan"
                                            >{{ prog.keterangan }}</p>
                                        </div>

                                        <div class="relative" :style="{ width: totalTimelineWidth + 'px', height: '28px' }">
                                            <div
                                                v-for="(m, mi) in months" :key="mi"
                                                class="absolute inset-y-0 w-px bg-border/25"
                                                :style="{ left: m.left + 'px' }"
                                            />
                                            <!-- Rencana bar (background) -->
                                            <div
                                                class="absolute inset-y-2 rounded-sm bg-slate-200 dark:bg-slate-700"
                                                :style="progressBarStyle(prog)"
                                            />
                                            <!-- Realisasi overlay -->
                                            <div
                                                class="absolute inset-y-2 overflow-hidden rounded-sm"
                                                :style="{
                                                    ...progressBarStyle(prog),
                                                    clipPath: `inset(0 ${100 - Math.min((prog.rencana > 0 ? prog.realisasi / prog.rencana : 0) * 100, 100)}% 0 0)`
                                                }"
                                            >
                                                <div
                                                    class="h-full w-full rounded-sm"
                                                    :class="
                                                        prog.realisasi >= prog.rencana
                                                            ? 'bg-green-400 dark:bg-green-600'
                                                            : prog.realisasi >= prog.rencana * 0.8
                                                                ? 'bg-yellow-400 dark:bg-yellow-600'
                                                                : 'bg-red-400 dark:bg-red-600'
                                                    "
                                                />
                                            </div>
                                            <!-- Label on bar -->
                                            <div
                                                class="absolute inset-y-1.5 flex items-center overflow-hidden px-1 text-[9px] font-medium leading-none"
                                                :style="progressBarStyle(prog)"
                                                :title="`${prog.label} — Rencana: ${prog.rencana}%, Realisasi: ${prog.realisasi}%`"
                                            >
                                                <span class="truncate opacity-70">R:{{ prog.rencana }}% A:{{ prog.realisasi }}%</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ── Sub-uraian (children) rows ── -->
                                    <div
                                        v-for="(child, ci) in prog.children"
                                        :key="`child-${ii}-${pi}-${ci}`"
                                        class="flex items-center border-b border-border/10 bg-sky-50/30 dark:bg-sky-950/10"
                                    >
                                        <div class="flex w-52 shrink-0 flex-col justify-center gap-0.5 border-r py-1 pl-11 pr-3 text-[10px] text-muted-foreground">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="inline-block h-1 w-1 shrink-0 rounded-full"
                                                    :class="child.not_started ? 'bg-gray-400' : 'bg-sky-400'"
                                                />
                                                <span class="min-w-0 truncate italic">{{ child.label || 'Sub-uraian' }}</span>
                                                <span
                                                    v-if="child.not_started"
                                                    class="ml-auto shrink-0 rounded-full bg-gray-100 px-1 py-0.5 text-[9px] text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                                                >Not Started</span>
                                                <span
                                                    v-else
                                                    class="ml-auto shrink-0"
                                                    :class="
                                                        child.realisasi >= child.rencana
                                                            ? 'text-green-600'
                                                            : 'text-yellow-600'
                                                    "
                                                >{{ child.realisasi }}%</span>
                                            </div>
                                            <p
                                                v-if="child.keterangan"
                                                class="truncate pl-3 text-[9px] italic text-muted-foreground/50"
                                                :title="child.keterangan"
                                            >{{ child.keterangan }}</p>
                                        </div>

                                        <div class="relative" :style="{ width: totalTimelineWidth + 'px', height: '22px' }">
                                            <div
                                                v-for="(m, mi) in months" :key="mi"
                                                class="absolute inset-y-0 w-px bg-border/20"
                                                :style="{ left: m.left + 'px' }"
                                            />
                                            <div
                                                class="absolute inset-y-1.5 rounded-sm bg-sky-200/60 dark:bg-sky-800/40"
                                                :style="progressBarStyle(child)"
                                            />
                                            <div
                                                class="absolute inset-y-1.5 overflow-hidden rounded-sm"
                                                :style="{
                                                    ...progressBarStyle(child),
                                                    clipPath: `inset(0 ${100 - Math.min((child.rencana > 0 ? child.realisasi / child.rencana : 0) * 100, 100)}% 0 0)`
                                                }"
                                            >
                                                <div class="h-full w-full rounded-sm bg-sky-400 dark:bg-sky-600" />
                                            </div>
                                        </div>
                                    </div>

                                </template>
                            </template>
                        </template>

                    </div>
                </div>

                <div v-else class="p-8 text-center text-sm text-muted-foreground">
                    Belum ada data kontrak dengan tanggal untuk ditampilkan.
                </div>
            </div>

            <!-- ── Kurva S (multi-line per kontrak) ── -->
            <div class="rounded-xl border">
                <div class="border-b px-4 py-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-semibold">Kurva S</h2>
                            <p class="text-xs text-muted-foreground">Kumulatif rencana vs realisasi per kontrak</p>
                        </div>
                        <!-- Legend per kontrak -->
                        <div v-if="kurvaS.lines.length" class="flex flex-wrap gap-x-4 gap-y-1">
                            <div
                                v-for="(line, li) in kurvaS.lines"
                                :key="li"
                                class="flex items-center gap-1.5 text-xs"
                            >
                                <svg width="20" height="10">
                                    <line x1="0" y1="5" x2="20" y2="5" :stroke="lineColor(li)" stroke-width="2" stroke-dasharray="4 2" />
                                </svg>
                                <svg width="20" height="10">
                                    <line x1="0" y1="5" x2="20" y2="5" :stroke="lineColor(li)" stroke-width="2" />
                                </svg>
                                <span class="text-muted-foreground">{{ line.label }}</span>
                            </div>
                        </div>
                    </div>
                    <!-- Global legend hint -->
                    <div v-if="kurvaS.lines.length" class="mt-2 flex items-center gap-4 text-[10px] text-muted-foreground">
                        <span>— — putus-putus = Rencana</span>
                        <span>—— solid = Realisasi</span>
                    </div>
                </div>

                <div v-if="kurvaS.labels.length" class="overflow-x-auto p-2">
                    <svg
                        :viewBox="`0 0 ${SVG_W} ${SVG_H}`"
                        :style="{ minWidth: '480px', width: '100%', height: 'auto' }"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <!-- Y gridlines & labels -->
                        <template v-for="tick in yTicks" :key="tick">
                            <line
                                :x1="PAD.left" :y1="kurvaSY(tick)"
                                :x2="SVG_W - PAD.right" :y2="kurvaSY(tick)"
                                stroke="currentColor" stroke-opacity="0.1" stroke-width="1"
                            />
                            <text
                                :x="PAD.left - 6" :y="kurvaSY(tick) + 4"
                                text-anchor="end" font-size="10" fill="currentColor" fill-opacity="0.5"
                            >{{ tick }}%</text>
                        </template>

                        <!-- X labels (sampled to avoid crowding) -->
                        <template v-for="(label, i) in kurvaS.labels" :key="i">
                            <text
                                v-if="i % xLabelStep === 0"
                                :x="kurvaSX(i, kurvaS.labels.length)"
                                :y="SVG_H - PAD.bottom + 16"
                                text-anchor="middle" font-size="10"
                                fill="currentColor" fill-opacity="0.5"
                            >{{ label }}</text>
                        </template>

                        <!-- Axes -->
                        <line
                            :x1="PAD.left" :y1="PAD.top"
                            :x2="PAD.left" :y2="SVG_H - PAD.bottom"
                            stroke="currentColor" stroke-opacity="0.2" stroke-width="1"
                        />
                        <line
                            :x1="PAD.left" :y1="SVG_H - PAD.bottom"
                            :x2="SVG_W - PAD.right" :y2="SVG_H - PAD.bottom"
                            stroke="currentColor" stroke-opacity="0.2" stroke-width="1"
                        />

                        <!-- Lines per kontrak -->
                        <template v-for="(line, li) in kurvaS.lines" :key="li">
                            <!-- Rencana (dashed) -->
                            <polyline
                                v-for="(seg, si) in buildSegments(line.rencana, kurvaS.labels.length)"
                                :key="`r-seg-${li}-${si}`"
                                :points="seg"
                                fill="none"
                                :stroke="lineColor(li)"
                                stroke-width="1.5"
                                stroke-dasharray="5 3"
                                stroke-opacity="0.6"
                                stroke-linejoin="round"
                            />

                            <!-- Realisasi (solid) -->
                            <polyline
                                v-for="(seg, si) in buildSegments(line.realisasi, kurvaS.labels.length)"
                                :key="`a-seg-${li}-${si}`"
                                :points="seg"
                                fill="none"
                                :stroke="lineColor(li)"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />

                            <!-- Realisasi dots with tooltip -->
                            <circle
                                v-for="(val, i) in line.realisasi"
                                :key="`dot-${li}-${i}`"
                                v-show="val !== null"
                                :cx="kurvaSX(i, kurvaS.labels.length)"
                                :cy="val !== null ? kurvaSY(val) : 0"
                                r="3"
                                :fill="lineColor(li)"
                                stroke="white"
                                stroke-width="1"
                            >
                                <title>{{ line.label }} – {{ kurvaS.labels[i] }}: Realisasi {{ val }}%</title>
                            </circle>
                        </template>
                    </svg>
                </div>

                <div v-else class="p-8 text-center text-sm text-muted-foreground">
                    Belum ada data progress. Silakan input progress di menu <strong>Monev → Progress</strong>.
                </div>
            </div>

        </div>
    </AppLayout>
</template>
