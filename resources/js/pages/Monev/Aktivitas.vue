<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type UraianKegiatan = {
    id: number;
    aktivitas_id: number;
    uraian_kegiatan: string;
    volume: string | null;
    anggaran_rab: number;
    anggaran_hps: number;
    kak_no: string | null;
    kak_spesifikasi: string | null;
};

type Aktivitas = {
    id: number;
    jenis_kegiatan: string;
    uraian_kegiatan: UraianKegiatan[];
};

type InstansiOption = { id: number; nama_instansi: string };
type UnitKerjaOption = { id: number; nama_unit_kerja: string };

const props = defineProps<{
    aktivitas: Aktivitas[];
    instansiList: InstansiOption[];
    unitKerjaList: UnitKerjaOption[];
    selectedInstansi: number | null;
    selectedUnit: number | null;
    anggaranPengadaan: number | null;
    totalRab: number;
    tahun: number;
    isAdmin: boolean;
    canCrud: boolean;
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug ?? '');
function monevRoute(path: string) { return `/${teamSlug.value}/monev${path}`; }

// ── Filter ────────────────────────────────────────────────────────────────────
const filterInstansi = ref<number | null>(props.selectedInstansi);
const filterUnit     = ref<number | null>(props.selectedUnit);

watch(filterInstansi, (val) => {
    filterUnit.value = null;
    if (val) {
        router.get(monevRoute('/aktivitas'), { instansi_id: val }, { preserveState: true, replace: true });
    } else {
        router.get(monevRoute('/aktivitas'), {}, { preserveState: true, replace: true });
    }
});

watch(filterUnit, (val) => {
    if (val) {
        router.get(monevRoute('/aktivitas'), { instansi_id: filterInstansi.value, unit_kerja_id: val }, { preserveState: true, replace: true });
    } else if (filterInstansi.value) {
        router.get(monevRoute('/aktivitas'), { instansi_id: filterInstansi.value }, { preserveState: true, replace: true });
    }
});

// Pastikan URL param instansi_id terisi saat admin pertama kali membuka halaman
onMounted(() => {
    if (props.isAdmin && filterInstansi.value) {
        const url = new URL(window.location.href);
        if (!url.searchParams.get('instansi_id')) {
            router.get(monevRoute('/aktivitas'),
                { instansi_id: filterInstansi.value },
                { preserveState: true, replace: true });
        }
    }
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Aktivitas', href: '#' }];

// ─── Modal Aktivitas (Tambah / Edit Jenis Kegiatan) ───────────────────────────
type AktivitasModal = 'add' | 'edit' | null;
const aktivitasModal = ref<AktivitasModal>(null);
const selectedAktivitas = ref<Aktivitas | null>(null);

const formAktivitas = useForm({ jenis_kegiatan: '', unit_kerja_id: null as number | null });

function openTambahAktivitas() {
    formAktivitas.reset();
    formAktivitas.unit_kerja_id = filterUnit.value ?? (filterInstansi.value ? null : null);
    aktivitasModal.value = 'add';
}

function openEditAktivitas(item: Aktivitas) {
    selectedAktivitas.value = item;
    formAktivitas.jenis_kegiatan = item.jenis_kegiatan;
    aktivitasModal.value = 'edit';
}

function closeAktivitasModal() {
    aktivitasModal.value = null;
    selectedAktivitas.value = null;
    formAktivitas.reset();
    formAktivitas.clearErrors();
}

function submitTambahAktivitas() {
    formAktivitas.post(monevRoute('/aktivitas'), { onSuccess: () => closeAktivitasModal() });
}

function submitEditAktivitas() {
    if (!selectedAktivitas.value) return;
    formAktivitas.put(monevRoute(`/aktivitas/${selectedAktivitas.value.id}`), {
        onSuccess: () => closeAktivitasModal(),
    });
}

function hapusAktivitas(id: number) {
    if (!confirm('Hapus jenis kegiatan ini beserta semua uraian kegiatannya?')) return;
    router.delete(monevRoute(`/aktivitas/${id}`));
}

// ─── Modal View Uraian Kegiatan ───────────────────────────────────────────────
const viewModal = ref(false);
const viewAktivitasId = ref<number | null>(null);

// computed agar otomatis sinkron saat data Inertia direfresh
const viewAktivitas = computed(() =>
    viewAktivitasId.value
        ? props.aktivitas.find(a => a.id === viewAktivitasId.value) ?? null
        : null
);

function openViewUraian(item: Aktivitas) {
    viewAktivitasId.value = item.id;
    viewModal.value = true;
}

function closeViewModal() {
    viewModal.value = false;
    viewAktivitasId.value = null;
}

// ─── Modal Tambah / Edit Uraian Kegiatan ─────────────────────────────────────
type UraianModal = 'add' | 'edit' | null;
const uraianModal = ref<UraianModal>(null);
const uraianAktivitasId = ref<number | null>(null);
const selectedUraian = ref<UraianKegiatan | null>(null);

const satuanOptions = ['Unit', 'Buah', 'Box', 'Set', 'Paket', 'Lembar', 'Kg', 'Gram', 'Meter', 'Liter', 'Rim', 'Eksemplar'];

const inputMode = ref<'manual' | 'upload'>('manual');

// Volume dipecah jadi jumlah + satuan
const volumeJumlah = ref('');
const volumeSatuan = ref('Unit');

// Anggaran pakai ref terpisah agar tidak tergantung custom Input component
const anggaranRab = ref<number | null>(null);
const anggaranHps = ref<number | null>(null);

function parseVolume(vol: string | null) {
    if (!vol) return { jumlah: '', satuan: 'Unit' };
    const parts = vol.trim().split(' ');
    const jumlah = parts[0] ?? '';
    const satuan = parts.slice(1).join(' ') || 'Unit';
    return { jumlah, satuan };
}

const formUraian = useForm({
    uraian_kegiatan: '',
    volume: '',
    anggaran_rab: '',
    anggaran_hps: '',
    kak_no: '',
    kak_spesifikasi: '',
});

const formImport = useForm({
    file: null as File | null,
});

function openTambahUraian(aktivitas: Aktivitas) {
    uraianAktivitasId.value = aktivitas.id;
    formUraian.reset();
    inputMode.value = 'manual';
    volumeJumlah.value = '';
    volumeSatuan.value = 'Unit';
    anggaranRab.value = null;
    anggaranHps.value = null;
    uraianModal.value = 'add';
}

function openEditUraian(uraian: UraianKegiatan) {
    selectedUraian.value = uraian;
    formUraian.uraian_kegiatan = uraian.uraian_kegiatan;
    formUraian.kak_no = uraian.kak_no ?? '';
    formUraian.kak_spesifikasi = uraian.kak_spesifikasi ?? '';
    formImport.reset();
    const { jumlah, satuan } = parseVolume(uraian.volume);
    volumeJumlah.value = jumlah;
    volumeSatuan.value = satuanOptions.includes(satuan) ? satuan : 'Unit';
    anggaranRab.value = uraian.anggaran_rab ? Number(uraian.anggaran_rab) : null;
    anggaranHps.value = uraian.anggaran_hps ? Number(uraian.anggaran_hps) : null;
    inputMode.value = 'manual';
    uraianModal.value = 'edit';
}

function closeUraianModal() {
    uraianModal.value = null;
    uraianAktivitasId.value = null;
    selectedUraian.value = null;
    volumeJumlah.value = '';
    volumeSatuan.value = 'Unit';
    anggaranRab.value = null;
    anggaranHps.value = null;
    inputMode.value = 'manual';
    limitError.value = '';
    formUraian.reset();
    formUraian.clearErrors();
    formImport.reset();
    formImport.clearErrors();
}

function buildVolume() {
    if (!volumeJumlah.value) return '';
    return `${volumeJumlah.value} ${volumeSatuan.value}`;
}

function submitTambahUraian() {
    if (!uraianAktivitasId.value) return;
    limitError.value = '';

    if (inputMode.value === 'manual') {
        // Validasi limit anggaran pengadaan
        if (sisaLimit.value !== null && (anggaranRab.value ?? 0) > 0) {
            const newRab = Number(anggaranRab.value ?? 0);
            if (newRab > sisaLimit.value) {
                limitError.value = `Proses tidak dapat dilanjutkan karena nominal RAB (${formatRupiah(newRab)}) melebihi sisa anggaran pengadaan (${formatRupiah(sisaLimit.value)}).`;
                return;
            }
        }
        formUraian.volume = buildVolume();
        formUraian.anggaran_rab = String(anggaranRab.value ?? 0);
        formUraian.anggaran_hps = String(anggaranHps.value ?? 0);
    }
    formUraian.post(monevRoute(`/aktivitas/${uraianAktivitasId.value}/uraian`), {
        onSuccess: () => closeUraianModal(),
    });
}

function submitEditUraian() {
    if (!selectedUraian.value) return;
    if (inputMode.value === 'manual') {
        formUraian.volume = buildVolume();
        formUraian.anggaran_rab = String(anggaranRab.value ?? 0);
        formUraian.anggaran_hps = String(anggaranHps.value ?? 0);
    }
    formUraian.put(monevRoute(`/uraian/${selectedUraian.value.id}`), {
        onSuccess: () => closeUraianModal(),
    });
}

function submitImportUraian() {
    if (!uraianAktivitasId.value) return;
    formImport.post(monevRoute(`/aktivitas/${uraianAktivitasId.value}/uraian/import`), {
        onSuccess: () => closeUraianModal(),
    });
}

function hapusUraian(id: number) {
    if (!confirm('Hapus uraian kegiatan ini?')) return;
    router.delete(monevRoute(`/uraian/${id}`));
}

// ─── Search + Pagination ───────────────────────────────────────────────────────
const search = ref('');
const filteredAktivitas = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.aktivitas;
    return props.aktivitas.filter(a =>
        a.jenis_kegiatan.toLowerCase().includes(q) ||
        a.uraian_kegiatan.some(u => u.uraian_kegiatan.toLowerCase().includes(q))
    );
});

const PAGE_SIZE = 7;
const currentPage = ref(1);
watch(filteredAktivitas, () => { currentPage.value = 1; });

const totalPages = computed(() => Math.max(1, Math.ceil(filteredAktivitas.value.length / PAGE_SIZE)));
const paginatedAktivitas = computed(() =>
    filteredAktivitas.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE)
);

// ─── Limit Anggaran ──────────────────────────────────────────────────────────
const sisaLimit = computed(() => {
    if (props.anggaranPengadaan === null) return null;
    return props.anggaranPengadaan - props.totalRab;
});

// Error notif untuk melebihi limit
const limitError = ref('');

// ─── Format ──────────────────────────────────────────────────────────────────
function formatRupiah(value: number | string | null) {
    if (!value && value !== 0) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value));
}
</script>

<template>
    <Head title="Aktivitas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">

            <!-- Filter Bar (admin only) -->
            <div v-if="isAdmin" class="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/30 px-4 py-3">
                <span class="text-xs font-semibold text-muted-foreground">Filter:</span>
                <select
                    v-model="filterInstansi"
                    class="h-8 rounded-md border border-input bg-background px-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                >
                    <option :value="null">— Pilih Instansi —</option>
                    <option v-for="ins in instansiList" :key="ins.id" :value="ins.id">
                        {{ ins.nama_instansi }}
                    </option>
                </select>
                <select
                    v-model="filterUnit"
                    :disabled="!filterInstansi"
                    class="h-8 rounded-md border border-input bg-background px-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50"
                >
                    <option :value="null">— Pilih Unit Kerja —</option>
                    <option v-for="uk in unitKerjaList" :key="uk.id" :value="uk.id">
                        {{ uk.nama_unit_kerja }}
                    </option>
                </select>
                <button
                    v-if="filterInstansi"
                    class="h-8 rounded-md border border-input px-2 text-xs text-muted-foreground hover:bg-muted"
                    @click="filterInstansi = null"
                >Reset</button>
            </div>

            <!-- Blank state: no instansi (admin only) -->
            <div v-if="isAdmin && !filterInstansi" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                <svg class="mb-3 size-10 text-muted-foreground/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7h18M3 12h18M3 17h18" />
                </svg>
                <p class="text-sm font-medium text-muted-foreground">Pilih instansi untuk melihat data aktivitas</p>
                <p class="mt-1 text-xs text-muted-foreground/70">Gunakan filter di atas untuk memilih instansi</p>
            </div>

            <!-- Blank state: instansi selected but no unit kerja (admin only) -->
            <div v-else-if="isAdmin && filterInstansi && !filterUnit" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                <svg class="mb-3 size-10 text-muted-foreground/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                </svg>
                <p class="text-sm font-medium text-muted-foreground">Pilih unit kerja untuk melihat data aktivitas</p>
                <p class="mt-1 text-xs text-muted-foreground/70">Instansi sudah dipilih, sekarang pilih unit kerja</p>
            </div>

            <template v-if="filterUnit || !isAdmin">

            <!-- Limit Anggaran Banner -->
            <div v-if="anggaranPengadaan !== null" class="grid grid-cols-3 gap-3 rounded-xl border bg-muted/20 p-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Anggaran Pengadaan {{ tahun }}</p>
                    <p class="mt-0.5 text-lg font-bold">{{ formatRupiah(anggaranPengadaan) }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Total RAB Diinput</p>
                    <p class="mt-0.5 text-lg font-bold">{{ formatRupiah(totalRab) }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Sisa Limit</p>
                    <p
                        class="mt-0.5 text-lg font-bold"
                        :class="(sisaLimit ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : (sisaLimit ?? 0) === 0 ? 'text-orange-500' : 'text-green-600 dark:text-green-400'"
                    >{{ formatRupiah(sisaLimit) }}</p>
                    <p v-if="(sisaLimit ?? 0) < 0" class="mt-0.5 text-[10px] text-red-500">⚠ RAB melebihi anggaran pengadaan</p>
                </div>
            </div>
            <div v-else-if="filterUnit" class="rounded-xl border border-dashed px-4 py-3 text-xs text-muted-foreground">
                Anggaran pengadaan tahun {{ tahun }} belum diatur untuk unit kerja ini. Atur melalui Dashboard.
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Daftar Aktivitas</h1>
                <div class="flex items-center gap-2">
                    <Input
                        v-model="search"
                        placeholder="Cari aktivitas..."
                        class="h-8 w-52 text-sm"
                    />
                    <Button v-if="canCrud" size="sm" @click="openTambahAktivitas">+ Tambah Aktivitas</Button>
                </div>
            </div>

            <!-- Tabel Jenis Kegiatan -->
            <div class="overflow-auto max-h-[60svh] rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-muted/60">
                        <tr>
                            <th class="w-12 border-b p-3 text-center font-semibold">No</th>
                            <th class="border-b p-3 text-left font-semibold">Jenis Kegiatan</th>
                            <th class="w-48 border-b p-3 text-right font-semibold">Jumlah RAB</th>
                            <th class="w-56 border-b p-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, index) in paginatedAktivitas"
                            :key="item.id"
                            class="border-b transition-colors last:border-0 hover:bg-muted/30"
                        >
                            <td class="p-3 text-center">{{ (currentPage - 1) * PAGE_SIZE + index + 1 }}</td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <span>{{ item.jenis_kegiatan }}</span>
                                    <span
                                        v-if="item.uraian_kegiatan.length"
                                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                    >
                                        {{ item.uraian_kegiatan.length }} uraian
                                    </span>
                                </div>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                {{ formatRupiah(item.uraian_kegiatan.reduce((s, u) => s + Number(u.anggaran_rab), 0)) }}
                            </td>
                            <td class="p-3">
                                <div class="flex justify-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        title="Lihat Uraian"
                                        @click="openViewUraian(item)"
                                    >
                                        <Eye class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canCrud"
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        title="Edit Jenis Kegiatan"
                                        @click="openEditAktivitas(item)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canCrud"
                                        variant="destructive"
                                        size="icon"
                                        class="size-7"
                                        title="Hapus"
                                        @click="hapusAktivitas(item.id)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canCrud"
                                        size="icon"
                                        class="size-7"
                                        title="Tambah Uraian"
                                        @click="openTambahUraian(item)"
                                    >
                                        <Plus class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredAktivitas.length">
                            <td colspan="4" class="p-8 text-center text-muted-foreground">
                                {{ search ? 'Tidak ada aktivitas yang cocok.' : 'Belum ada data. Klik "Tambah Aktivitas" untuk memulai.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination v-model:currentPage="currentPage" :totalPages="totalPages" />

            </template><!-- end v-if="filterInstansi && filterUnit" -->
        </div>

        <!-- ── Modal Tambah / Edit Jenis Kegiatan ── -->
        <Dialog
            :open="aktivitasModal === 'add' || aktivitasModal === 'edit'"
            @update:open="(v) => !v && closeAktivitasModal()"
        >
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        {{ aktivitasModal === 'add' ? 'Tambah Jenis Kegiatan' : 'Edit Jenis Kegiatan' }}
                    </DialogTitle>
                </DialogHeader>

                <div class="grid gap-2 py-2">
                    <Label>Jenis Kegiatan <span class="text-destructive">*</span></Label>
                    <Input
                        v-model="formAktivitas.jenis_kegiatan"
                        placeholder="Masukkan jenis kegiatan"
                        :class="formAktivitas.errors.jenis_kegiatan ? 'border-destructive' : ''"
                        @keyup.enter="aktivitasModal === 'add' ? submitTambahAktivitas() : submitEditAktivitas()"
                    />
                    <p v-if="formAktivitas.errors.jenis_kegiatan" class="text-xs text-destructive">
                        {{ formAktivitas.errors.jenis_kegiatan }}
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeAktivitasModal">Batal</Button>
                    <Button
                        :disabled="formAktivitas.processing"
                        @click="aktivitasModal === 'add' ? submitTambahAktivitas() : submitEditAktivitas()"
                    >
                        {{ formAktivitas.processing ? 'Menyimpan...' : aktivitasModal === 'add' ? 'Simpan' : 'Update' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Modal View Uraian Kegiatan ── -->
        <Dialog :open="viewModal" @update:open="(v) => !v && closeViewModal()">
            <DialogContent class="sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        Uraian Kegiatan — {{ viewAktivitas?.jenis_kegiatan }}
                    </DialogTitle>
                </DialogHeader>

                <div class="max-h-[60vh] overflow-y-auto">
                    <table
                        v-if="viewAktivitas && viewAktivitas.uraian_kegiatan.length"
                        class="w-full text-sm"
                    >
                        <thead class="sticky top-0 bg-muted/80">
                            <tr>
                                <th class="border-b p-2 text-center">No</th>
                                <th class="border-b p-2 text-left">Uraian Kegiatan</th>
                                <th class="border-b p-2 text-left">Volume</th>
                                <th class="border-b p-2 text-left">RAB</th>
                                <th class="border-b p-2 text-left">HPS</th>
                                <th class="border-b p-2 text-left">KAK No</th>
                                <th class="border-b p-2 text-left">Spesifikasi</th>
                                <th class="border-b p-2 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(u, idx) in viewAktivitas.uraian_kegiatan"
                                :key="u.id"
                                class="border-b last:border-0 hover:bg-muted/30"
                            >
                                <td class="p-2 text-center">{{ idx + 1 }}</td>
                                <td class="p-2">{{ u.uraian_kegiatan }}</td>
                                <td class="p-2">{{ u.volume || '-' }}</td>
                                <td class="p-2 whitespace-nowrap">{{ formatRupiah(u.anggaran_rab) }}</td>
                                <td class="p-2 whitespace-nowrap">{{ formatRupiah(u.anggaran_hps) }}</td>
                                <td class="p-2">{{ u.kak_no || '-' }}</td>
                                <td class="p-2">{{ u.kak_spesifikasi || '-' }}</td>
                                <td class="p-2">
                                    <div class="flex justify-center gap-1">
                                        <Button
                                            v-if="canCrud"
                                            variant="outline"
                                            size="icon"
                                            class="size-6"
                                            title="Edit"
                                            @click="openEditUraian(u)"
                                        >
                                            <Pencil class="size-3" />
                                        </Button>
                                        <Button
                                            v-if="canCrud"
                                            variant="destructive"
                                            size="icon"
                                            class="size-6"
                                            title="Hapus"
                                            @click="hapusUraian(u.id)"
                                        >
                                            <Trash2 class="size-3" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="py-8 text-center text-muted-foreground text-sm">
                        {{ canCrud ? 'Belum ada uraian kegiatan. Klik tombol "+ Uraian" untuk menambahkan.' : 'Belum ada uraian kegiatan.' }}
                    </div>
                </div>

                <DialogFooter>
                    <Button @click="closeViewModal">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Modal Tambah / Edit Uraian Kegiatan ── -->
        <Dialog
            :open="uraianModal === 'add' || uraianModal === 'edit'"
            @update:open="(v) => !v && closeUraianModal()"
        >
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ uraianModal === 'add' ? 'Tambah Uraian Kegiatan' : 'Edit Uraian Kegiatan' }}
                    </DialogTitle>
                </DialogHeader>

                <div class="grid gap-3 py-2">
                    <!-- Toggle Mode (hanya saat Tambah) -->
                    <div v-if="uraianModal === 'add'" class="flex rounded-md border overflow-hidden text-sm">
                        <button
                            type="button"
                            class="flex-1 py-1.5 font-medium transition-colors"
                            :class="inputMode === 'manual' ? 'bg-primary text-primary-foreground' : 'bg-transparent text-muted-foreground hover:bg-muted/50'"
                            @click="inputMode = 'manual'"
                        >Isi Manual</button>
                        <button
                            type="button"
                            class="flex-1 py-1.5 font-medium transition-colors"
                            :class="inputMode === 'upload' ? 'bg-primary text-primary-foreground' : 'bg-transparent text-muted-foreground hover:bg-muted/50'"
                            @click="inputMode = 'upload'"
                        >Upload File</button>
                    </div>

                    <!-- Upload Mode: file Excel saja -->
                    <template v-if="inputMode === 'upload'">
                        <div class="grid gap-1.5">
                            <Label>File Excel (.xlsx / .xls) <span class="text-destructive">*</span></Label>
                            <input
                                type="file"
                                accept=".xlsx,.xls"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-sm file:border-0 file:bg-transparent file:text-sm file:font-medium"
                                :class="formImport.errors.file ? 'border-destructive' : ''"
                                @change="(e) => { const f = (e.target as HTMLInputElement).files?.[0]; formImport.file = f ?? null; }"
                            />
                            <p v-if="formImport.errors.file" class="text-xs text-destructive">
                                {{ formImport.errors.file }}
                            </p>
                            <div class="rounded-md bg-muted/50 p-3 text-xs text-muted-foreground space-y-1">
                                <p class="font-medium text-foreground">Format kolom Excel (baris 1 = header, baris 2+ = data):</p>
                                <p>A: Uraian Kegiatan &nbsp;|&nbsp; B: Vol. Jumlah &nbsp;|&nbsp; C: Vol. Satuan &nbsp;|&nbsp; D: Anggaran RAB &nbsp;|&nbsp; E: Anggaran HPS &nbsp;|&nbsp; F: KAK No &nbsp;|&nbsp; G: KAK Spesifikasi</p>
                            </div>
                        </div>
                    </template>

                    <!-- Manual Mode: semua field detail -->
                    <template v-else>
                        <div class="grid gap-1.5">
                            <Label>Uraian Kegiatan <span class="text-destructive">*</span></Label>
                            <textarea
                                v-model="formUraian.uraian_kegiatan"
                                rows="2"
                                placeholder="Masukkan uraian kegiatan"
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                :class="formUraian.errors.uraian_kegiatan ? 'border-destructive' : ''"
                            />
                            <p v-if="formUraian.errors.uraian_kegiatan" class="text-xs text-destructive">
                                {{ formUraian.errors.uraian_kegiatan }}
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Volume</Label>
                            <div class="flex gap-2">
                                <Input
                                    v-model="volumeJumlah"
                                    type="number"
                                    min="0"
                                    placeholder="Jumlah"
                                    class="w-28"
                                />
                                <select
                                    v-model="volumeSatuan"
                                    class="flex h-9 flex-1 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option v-for="s in satuanOptions" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label>Anggaran RAB (Rp)</Label>
                                <input
                                    v-model.number="anggaranRab"
                                    type="number"
                                    min="0"
                                    step="any"
                                    placeholder="0"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Anggaran HPS (Rp)</Label>
                                <input
                                    v-model.number="anggaranHps"
                                    type="number"
                                    min="0"
                                    step="any"
                                    placeholder="0"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label>KAK No</Label>
                                <Input v-model="formUraian.kak_no" placeholder="Nomor KAK" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>KAK Spesifikasi</Label>
                                <textarea
                                    v-model="formUraian.kak_spesifikasi"
                                    rows="2"
                                    placeholder="Spesifikasi teknis"
                                    class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Notif limit terlampaui -->
                <div
                    v-if="limitError"
                    class="rounded-lg border border-red-300 bg-red-50 px-3 py-2.5 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-400"
                >
                    🚫 {{ limitError }}
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeUraianModal">Batal</Button>
                    <template v-if="inputMode === 'upload'">
                        <Button
                            :disabled="formImport.processing || !formImport.file"
                            @click="submitImportUraian"
                        >
                            {{ formImport.processing ? 'Mengimpor...' : 'Import' }}
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            :disabled="formUraian.processing"
                            @click="uraianModal === 'add' ? submitTambahUraian() : submitEditUraian()"
                        >
                            {{ formUraian.processing ? 'Menyimpan...' : uraianModal === 'add' ? 'Simpan' : 'Update' }}
                        </Button>
                    </template>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
