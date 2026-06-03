<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, Pencil, Trash2, FileDown, Paperclip, X } from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Vendor = {
    id: number;
    jenis_vendor: 'PT' | 'CV' | 'Pribadi';
    nama_vendor: string;
    direktur: string | null;
    no_hp: string | null;
};

type UraianKegiatan = {
    id: number;
    uraian_kegiatan: string;
    anggaran_hps: number;
    anggaran_rab: number;
    aktivitas: { id: number; jenis_kegiatan: string };
};

type Kontrak = {
    id: number;
    no_kontrak: string;
    tanggal_kontrak: string | null;
    uraian_pekerjaan: string;
    nominal_kontrak: number;
    uraian_kegiatan_id: number | null;
    vendor_id: number | null;
    pelaksana: string | null;
    no_hp_pelaksana: string | null;
    tanggal_mulai: string | null;
    tanggal_akhir: string | null;
    vendor: Vendor | null;
    uraian_kegiatan: UraianKegiatan | null;
    dokumen_url: string | null;
    dokumen_name: string | null;
};

type InstansiOption = { id: number; nama_instansi: string };
type UnitKerjaOption = { id: number; nama_unit_kerja: string };

const props = defineProps<{
    kontrak: Kontrak[];
    uraianKegiatan: UraianKegiatan[];
    vendors: Vendor[];
    instansiList: InstansiOption[];
    unitKerjaList: UnitKerjaOption[];
    selectedInstansi: number | null;
    selectedUnit: number | null;
    selectedUnitName: string | null;
    isAdmin: boolean;
    canEdit: boolean;
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug ?? '');
function monevRoute(path: string) { return `/${teamSlug.value}/monev${path}`; }

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Kontrak', href: '#' }];

// ── Filter ────────────────────────────────────────────────────────────────────
const filterInstansi = ref<number | null>(props.selectedInstansi);
const filterUnit     = ref<number | null>(props.selectedUnit);

watch(filterInstansi, (val) => {
    filterUnit.value = null;
    const params = val ? { instansi_id: val } : {};
    router.get(monevRoute('/kontrak'), params, { preserveState: true, replace: true });
});

watch(filterUnit, (val) => {
    if (val) {
        router.get(monevRoute('/kontrak'), { instansi_id: filterInstansi.value, unit_kerja_id: val }, { preserveState: true, replace: true });
    } else if (filterInstansi.value) {
        router.get(monevRoute('/kontrak'), { instansi_id: filterInstansi.value }, { preserveState: true, replace: true });
    }
});

// ─── Modal ───────────────────────────────────────────────────────────────────
type ModalType = 'add' | 'edit' | 'view' | null;
const modalType = ref<ModalType>(null);
const selectedItem = ref<Kontrak | null>(null);

// Nominal pakai ref terpisah agar v-model.number bisa sync
const nominalKontrak = ref<number | null>(null);

const form = useForm({
    no_kontrak:          '',
    tanggal_kontrak:     '',
    uraian_pekerjaan:    '',
    nominal_kontrak:     '',
    uraian_kegiatan_id:  '' as string | number,
    // Vendor — diinput manual
    jenis_vendor:        'PT' as 'PT' | 'CV' | 'Pribadi',
    nama_vendor:         '',
    direktur:            '',
    no_hp:               '',
    pelaksana:           '',
    no_hp_pelaksana:     '',
    tanggal_mulai:       '',
    tanggal_akhir:       '',
    dokumen:             null as File | null,
    remove_dokumen:      false,
});

// ─── Vendor autocomplete ─────────────────────────────────────────────────────
const vendorDropdownOpen = ref(false);

const filteredVendorSuggestions = computed(() => {
    const q = form.nama_vendor.trim().toLowerCase();
    if (q.length < 2) return [];
    return props.vendors.filter(v =>
        v.jenis_vendor === form.jenis_vendor &&
        v.nama_vendor.toLowerCase().includes(q)
    );
});

function selectVendorSuggestion(v: Vendor) {
    form.nama_vendor = v.nama_vendor;
    form.direktur    = v.direktur ?? '';
    form.no_hp       = v.no_hp ?? '';
    vendorDropdownOpen.value = false;
}

function blurVendorInput() {
    setTimeout(() => { vendorDropdownOpen.value = false; }, 160);
}

// ─── Searchable dropdown: Jenis Kegiatan ─────────────────────────────────────
const selectedAktivitasId = ref<number | null>(null);
const jkSearch = ref('');
const jkOpen   = ref(false);

// daftar aktivitas unik dari uraianKegiatan
const aktivitasList = computed(() => {
    const map = new Map<number, { id: number; jenis_kegiatan: string }>();
    props.uraianKegiatan.forEach(u => {
        if (!map.has(u.aktivitas.id)) map.set(u.aktivitas.id, u.aktivitas);
    });
    return [...map.values()];
});

const filteredAktivitasOptions = computed(() => {
    const q = jkSearch.value.trim();
    if (q.length < 3) return aktivitasList.value;
    const lower = q.toLowerCase();
    return aktivitasList.value.filter(a => a.jenis_kegiatan.toLowerCase().includes(lower));
});

function blurJk() { setTimeout(() => { jkOpen.value = false }, 160); }
function blurUk() { setTimeout(() => { ukOpen.value = false }, 160); }

function selectAktivitas(a: { id: number; jenis_kegiatan: string }) {
    selectedAktivitasId.value = a.id;
    jkSearch.value            = a.jenis_kegiatan;
    jkOpen.value              = false;
    // reset uraian kegiatan saat jenis kegiatan diganti
    form.uraian_kegiatan_id   = '';
    ukSearch.value            = '';
}

// ─── Searchable dropdown: Uraian Kegiatan ────────────────────────────────────
const ukSearch = ref('');
const ukOpen   = ref(false);

const uraianForAktivitas = computed(() =>
    selectedAktivitasId.value
        ? props.uraianKegiatan.filter(u => u.aktivitas.id === selectedAktivitasId.value)
        : []
);

const filteredUraianOptions = computed(() => {
    const q = ukSearch.value.trim();
    if (q.length < 3) return uraianForAktivitas.value;
    const lower = q.toLowerCase();
    return uraianForAktivitas.value.filter(u => u.uraian_kegiatan.toLowerCase().includes(lower));
});

function selectUraian(u: UraianKegiatan) {
    form.uraian_kegiatan_id = u.id;
    ukSearch.value          = u.uraian_kegiatan;
    ukOpen.value            = false;
}

// ─── Computed dari pilihan uraian kegiatan ────────────────────────────────────
const selectedUraian = computed(() =>
    props.uraianKegiatan.find(u => u.id === Number(form.uraian_kegiatan_id)) ?? null
);

const persentaseBase = computed(() => {
    const hps = Number(selectedUraian.value?.anggaran_hps ?? 0);
    const rab = Number(selectedUraian.value?.anggaran_rab ?? 0);
    if (hps > 0) return { value: hps, label: 'HPS' };
    if (rab > 0) return { value: rab, label: 'RAB' };
    return null;
});

const persentase = computed(() => {
    const nominal = nominalKontrak.value ?? 0;
    if (!nominal || !persentaseBase.value) return null;
    return ((nominal / persentaseBase.value.value) * 100).toFixed(2);
});

// ─── Open / close ─────────────────────────────────────────────────────────────
function toDateInput(date: string | null): string {
    if (!date) return '';
    return date.slice(0, 10);
}

function openAdd() {
    form.reset();
    nominalKontrak.value      = null;
    selectedAktivitasId.value = null;
    jkSearch.value            = '';
    ukSearch.value            = '';
    jkOpen.value              = false;
    ukOpen.value              = false;
    modalType.value           = 'add';
}

function openEdit(item: Kontrak) {
    selectedItem.value       = item;
    form.no_kontrak          = item.no_kontrak;
    form.tanggal_kontrak     = toDateInput(item.tanggal_kontrak);
    form.uraian_pekerjaan    = item.uraian_pekerjaan;
    form.uraian_kegiatan_id  = item.uraian_kegiatan_id ?? '';
    // Vendor fields dari relasi vendor (auto-populated)
    form.jenis_vendor        = (item.vendor?.jenis_vendor ?? 'PT') as 'PT' | 'CV' | 'Pribadi';
    form.nama_vendor         = item.vendor?.nama_vendor ?? '';
    form.direktur            = item.vendor?.direktur ?? '';
    form.no_hp               = item.vendor?.no_hp ?? '';
    form.pelaksana           = item.pelaksana ?? '';
    form.no_hp_pelaksana     = item.no_hp_pelaksana ?? '';
    form.tanggal_mulai       = toDateInput(item.tanggal_mulai);
    form.tanggal_akhir       = toDateInput(item.tanggal_akhir);
    nominalKontrak.value     = item.nominal_kontrak ? Number(item.nominal_kontrak) : null;

    // set searchable dropdown ke nilai yang sudah ada
    if (item.uraian_kegiatan) {
        selectedAktivitasId.value = item.uraian_kegiatan.aktivitas.id;
        jkSearch.value            = item.uraian_kegiatan.aktivitas.jenis_kegiatan;
        ukSearch.value            = item.uraian_kegiatan.uraian_kegiatan;
    } else {
        selectedAktivitasId.value = null;
        jkSearch.value            = '';
        ukSearch.value            = '';
    }
    jkOpen.value    = false;
    ukOpen.value    = false;
    modalType.value = 'edit';
}

function openView(item: Kontrak) {
    selectedItem.value = item;
    modalType.value    = 'view';
}

function closeModal() {
    modalType.value           = null;
    selectedItem.value        = null;
    nominalKontrak.value      = null;
    selectedAktivitasId.value = null;
    jkSearch.value            = '';
    ukSearch.value            = '';
    jkOpen.value              = false;
    ukOpen.value              = false;
    form.reset();
    form.clearErrors();
}

// ─── Submit ───────────────────────────────────────────────────────────────────
function submitAdd() {
    form.nominal_kontrak  = String(nominalKontrak.value ?? 0);
    form.uraian_pekerjaan = selectedUraian.value?.uraian_kegiatan ?? '';
    form.post(monevRoute('/kontrak'), { onSuccess: () => closeModal() });
}

function submitEdit() {
    if (!selectedItem.value) return;
    form.nominal_kontrak  = String(nominalKontrak.value ?? 0);
    form.uraian_pekerjaan = selectedUraian.value?.uraian_kegiatan ?? '';
    form
        .transform((data) => ({ ...data, _method: 'PUT' }))
        .post(monevRoute(`/kontrak/${selectedItem.value!.id}`), { onSuccess: () => closeModal() });
}

function hapus(id: number) {
    if (!confirm('Yakin ingin menghapus kontrak ini?')) return;
    router.delete(monevRoute(`/kontrak/${id}`));
}

// ─── Format ───────────────────────────────────────────────────────────────────
function formatRupiah(value: number | string | null) {
    if (!value && value !== 0) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: 'IDR', minimumFractionDigits: 0,
    }).format(Number(value));
}

function formatDate(date: string | null) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

function namaVendor(item: Kontrak) {
    if (!item.vendor) return '-';
    if (item.vendor.jenis_vendor === 'Pribadi') return item.vendor.nama_vendor;
    return `${item.vendor.jenis_vendor} ${item.vendor.nama_vendor}`;
}

// ─── View modal computed ───────────────────────────────────────────────────────
const viewHpsOrRab = computed(() => {
    const uk = selectedItem.value?.uraian_kegiatan;
    if (!uk) return null;
    const hps = Number(uk.anggaran_hps ?? 0);
    const rab = Number(uk.anggaran_rab ?? 0);
    if (hps > 0) return { value: hps, label: 'HPS' };
    if (rab > 0) return { value: rab, label: 'RAB' };
    return null;
});

const viewPersentase = computed((): number | null => {
    if (!selectedItem.value || !viewHpsOrRab.value) return null;
    const nominal = Number(selectedItem.value.nominal_kontrak ?? 0);
    if (!nominal) return null;
    return Math.round(nominal / viewHpsOrRab.value.value * 1000) / 10;
});

const viewStatus = computed(() => {
    const item = selectedItem.value;
    if (!item) return null;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    if (item.tanggal_mulai && new Date(item.tanggal_mulai) > today)
        return { text: 'Belum Mulai', cls: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' };
    if (item.tanggal_akhir && new Date(item.tanggal_akhir) < today)
        return { text: 'Selesai', cls: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' };
    if (item.tanggal_mulai || item.tanggal_akhir)
        return { text: 'Berjalan', cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' };
    return null;
});

const viewDaysLeft = computed((): number | null => {
    if (!selectedItem.value?.tanggal_akhir) return null;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const end = new Date(selectedItem.value.tanggal_akhir);
    return Math.ceil((end.getTime() - today.getTime()) / 86400000);
});

const viewTimeElapsed = computed((): number | null => {
    const item = selectedItem.value;
    if (!item?.tanggal_mulai || !item?.tanggal_akhir) return null;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const start = new Date(item.tanggal_mulai);
    const end   = new Date(item.tanggal_akhir);
    const total = end.getTime() - start.getTime();
    if (total <= 0) return 100;
    const elapsed = Math.min(today.getTime() - start.getTime(), total);
    return Math.max(0, Math.round(elapsed / total * 100));
});

// ─── Batasan tahun berjalan ────────────────────────────────────────────────────
const tahunBerjalan = new Date().getFullYear();
const yearMin = `${tahunBerjalan}-01-01`;
const yearMax = `${tahunBerjalan}-12-31`;

// ─── Auto-fill tanggal_mulai dari tanggal_kontrak ─────────────────────────────
watch(() => form.tanggal_kontrak, (val) => {
    if (val && (!form.tanggal_mulai || modalType.value === 'add')) {
        form.tanggal_mulai = val;
    }
});

// ─── Auto-fill pelaksana for Pribadi (only in add mode) ──────────────────────
watch(() => form.jenis_vendor, (newType) => {
    if (newType === 'Pribadi' && modalType.value === 'add' && form.direktur) {
        form.pelaksana = form.direktur;
    }
});
watch(() => form.direktur, (newDirektur) => {
    if (form.jenis_vendor === 'Pribadi' && modalType.value === 'add') {
        form.pelaksana = newDirektur;
    }
});

// ─── Durasi kalender & hari kerja ─────────────────────────────────────────────
function countWorkingDays(start: string, end: string): number {
    let count = 0;
    const s = new Date(start);
    const e = new Date(end);
    s.setHours(0, 0, 0, 0);
    e.setHours(0, 0, 0, 0);
    const cur = new Date(s);
    while (cur <= e) {
        const day = cur.getDay();
        if (day !== 0 && day !== 6) count++;
        cur.setDate(cur.getDate() + 1);
    }
    return count;
}

// Durasi di form Add/Edit
const formDurasi = computed(() => {
    const s = form.tanggal_mulai;
    const e = form.tanggal_akhir;
    if (!s || !e || e < s) return null;
    const ms = new Date(e).getTime() - new Date(s).getTime();
    const calDays = Math.round(ms / 86400000) + 1;
    const workDays = countWorkingDays(s, e);
    return { calDays, workDays };
});

// ─── Search ───────────────────────────────────────────────────────────────────
const search = ref('');
const filteredKontrak = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.kontrak;
    return props.kontrak.filter(k =>
        k.no_kontrak.toLowerCase().includes(q) ||
        (k.uraian_pekerjaan && k.uraian_pekerjaan.toLowerCase().includes(q)) ||
        (k.vendor && (k.vendor.nama_vendor.toLowerCase().includes(q) || k.vendor.jenis_vendor.toLowerCase().includes(q))) ||
        (k.uraian_kegiatan && k.uraian_kegiatan.uraian_kegiatan.toLowerCase().includes(q)) ||
        (k.uraian_kegiatan?.aktivitas && k.uraian_kegiatan.aktivitas.jenis_kegiatan.toLowerCase().includes(q))
    );
});

// ─── Pagination ───────────────────────────────────────────────────────────────
const PAGE_SIZE = 7;
const currentPage = ref(1);
watch(filteredKontrak, () => { currentPage.value = 1; });
const totalPages = computed(() => Math.max(1, Math.ceil(filteredKontrak.value.length / PAGE_SIZE)));
const paginatedKontrak = computed(() =>
    filteredKontrak.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE)
);
</script>

<template>
    <Head title="Kontrak" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">

            <!-- Filter Bar (admin_mutu only) -->
            <div v-if="canEdit" class="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/30 px-4 py-3">
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

            <!-- Info bar: kepala_unit / staf — tampilkan nama unit kerja -->
            <div v-else-if="props.selectedUnitName" class="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/30 px-4 py-3">
                <span class="text-xs font-semibold text-muted-foreground">Unit Kerja:</span>
                <span class="flex h-8 items-center gap-1.5 rounded-md border border-input bg-background px-2.5 text-sm text-foreground">
                    {{ props.selectedUnitName }}
                </span>
            </div>

            <!-- Blank state: no instansi (admin_mutu only) -->
            <div v-if="canEdit && !filterInstansi" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                <svg class="mb-3 size-10 text-muted-foreground/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-3-3v6M3 7h18M3 12h18M3 17h18" />
                </svg>
                <p class="text-sm font-medium text-muted-foreground">Pilih instansi untuk melihat data kontrak</p>
                <p class="mt-1 text-xs text-muted-foreground/70">Gunakan filter di atas untuk memilih instansi</p>
            </div>

            <!-- Blank state: instansi selected, no unit kerja (admin_mutu only) -->
            <div v-else-if="canEdit && filterInstansi && !filterUnit" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                <svg class="mb-3 size-10 text-muted-foreground/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <p class="text-sm font-medium text-muted-foreground">Pilih unit kerja untuk melihat data kontrak</p>
                <p class="mt-1 text-xs text-muted-foreground/70">Gunakan filter unit kerja di atas</p>
            </div>

            <template v-if="filterUnit || !canEdit">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Daftar Kontrak</h1>
                <div class="flex items-center gap-2">
                    <Input
                        v-model="search"
                        placeholder="Cari kontrak..."
                        class="h-8 w-52 text-sm"
                    />
                    <a :href="monevRoute('/kontrak/export')" target="_blank">
                        <Button size="sm" variant="outline" type="button">
                            <FileDown class="mr-1.5 size-4" />
                            Download Excel
                        </Button>
                    </a>
                    <Button v-if="props.canEdit" size="sm" @click="openAdd">+ Tambah Kontrak</Button>
                </div>
            </div>

            <!-- Tabel -->
            <div class="overflow-auto max-h-[60svh] rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-muted/60">
                        <tr>
                            <th class="w-10 border-b p-3 text-center font-semibold">No</th>
                            <th class="w-32 border-b p-3 text-center font-semibold">No Kontrak</th>
                            <th class="w-32 border-b p-3 text-center font-semibold">Tgl Kontrak</th>
                            <th class="border-b p-3 text-center font-semibold">Uraian Pekerjaan</th>
                            <th class="w-36 border-b p-3 text-center font-semibold">Nominal</th>
                            <th class="w-40 border-b p-3 text-center font-semibold">Nama Vendor</th>
                            <th class="w-44 border-b p-3 text-center font-semibold">Waktu Pelaksanaan</th>
                            <th class="w-24 border-b p-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, index) in paginatedKontrak"
                            :key="item.id"
                            class="border-b transition-colors last:border-0 hover:bg-muted/30"
                        >
                            <td class="p-3 text-center">{{ (currentPage - 1) * PAGE_SIZE + index + 1 }}</td>
                            <td class="p-3 font-mono text-xs">{{ item.no_kontrak }}</td>
                            <td class="p-3 text-xs whitespace-nowrap">{{ formatDate(item.tanggal_kontrak) }}</td>
                            <td class="p-3">
                                <span class="line-clamp-2">{{ item.uraian_pekerjaan }}</span>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                {{ formatRupiah(item.nominal_kontrak) }}
                            </td>
                            <td class="p-3">{{ namaVendor(item) }}</td>
                            <td class="p-3 text-xs text-muted-foreground">
                                <template v-if="item.tanggal_mulai || item.tanggal_akhir">
                                    {{ formatDate(item.tanggal_mulai) }} – {{ formatDate(item.tanggal_akhir) }}
                                </template>
                                <span v-else>-</span>
                            </td>
                            <td class="p-3">
                                <div class="flex justify-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        title="Lihat Detail"
                                        @click="openView(item)"
                                    >
                                        <Eye class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="props.canEdit"
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        title="Edit"
                                        @click="openEdit(item)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="props.canEdit"
                                        variant="destructive"
                                        size="icon"
                                        class="size-7"
                                        title="Hapus"
                                        @click="hapus(item.id)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!paginatedKontrak.length">
                            <td colspan="8" class="p-8 text-center text-muted-foreground">
                                {{ search ? 'Tidak ada kontrak yang cocok.' : 'Belum ada data kontrak. Klik "Tambah Kontrak" untuk menambahkan.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination v-model:currentPage="currentPage" :totalPages="totalPages" />

            </template><!-- end v-if="filterInstansi && filterUnit" -->
        </div>

        <!-- ── Modal Tambah / Edit ── -->
        <Dialog
            :open="modalType === 'add' || modalType === 'edit'"
            @update:open="(v) => !v && closeModal()"
        >
            <DialogContent class="sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ modalType === 'add' ? 'Tambah Kontrak' : 'Edit Kontrak' }}
                    </DialogTitle>
                </DialogHeader>

                <div class="grid gap-3 py-2 max-h-[70vh] overflow-y-auto pr-1">

                    <!-- ① No Kontrak + Tanggal Kontrak (readonly di edit) -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label>No Kontrak <span v-if="modalType === 'add'" class="text-destructive">*</span></Label>
                            <template v-if="modalType === 'add'">
                                <Input
                                    v-model="form.no_kontrak"
                                    placeholder="Contoh: 001/SPK/2026"
                                    :class="form.errors.no_kontrak ? 'border-destructive' : ''"
                                />
                                <p v-if="form.errors.no_kontrak" class="text-xs text-destructive">{{ form.errors.no_kontrak }}</p>
                            </template>
                            <div v-else class="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm font-mono">
                                {{ selectedItem?.no_kontrak }}
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Tanggal Kontrak</Label>
                            <template v-if="modalType === 'add'">
                                <input
                                    v-model="form.tanggal_kontrak"
                                    type="date"
                                    :min="yearMin"
                                    :max="yearMax"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                            </template>
                            <div v-else class="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm">
                                {{ formatDate(selectedItem?.tanggal_kontrak ?? null) }}
                            </div>
                        </div>
                    </div>

                    <!-- ② Jenis Kegiatan + Uraian Kegiatan (readonly di edit, sebelahan) -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label>Jenis Kegiatan</Label>
                            <template v-if="modalType === 'add'">
                                <div class="relative">
                                    <input
                                        v-model="jkSearch"
                                        type="text"
                                        placeholder="Klik atau ketik min 3 huruf..."
                                        autocomplete="off"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        @focus="jkOpen = true"
                                        @blur="blurJk"
                                    />
                                    <div
                                        v-if="jkOpen"
                                        class="absolute z-50 mt-1 w-full rounded-md border bg-background shadow-lg max-h-48 overflow-y-auto"
                                    >
                                        <p v-if="!filteredAktivitasOptions.length" class="p-2 text-center text-xs text-muted-foreground">Tidak ada hasil</p>
                                        <button
                                            v-for="a in filteredAktivitasOptions"
                                            :key="a.id"
                                            type="button"
                                            class="w-full px-3 py-2 text-left text-sm hover:bg-muted/60 transition-colors"
                                            :class="selectedAktivitasId === a.id ? 'bg-primary/10 text-primary font-medium' : ''"
                                            @mousedown.prevent="selectAktivitas(a)"
                                        >{{ a.jenis_kegiatan }}</button>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm">
                                {{ selectedItem?.uraian_kegiatan?.aktivitas?.jenis_kegiatan || '-' }}
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Uraian Kegiatan</Label>
                            <template v-if="modalType === 'add'">
                                <div class="relative">
                                    <input
                                        v-model="ukSearch"
                                        type="text"
                                        :placeholder="selectedAktivitasId ? 'Klik atau ketik min 3 huruf...' : 'Pilih Jenis Kegiatan dulu'"
                                        :disabled="!selectedAktivitasId"
                                        autocomplete="off"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50 disabled:cursor-not-allowed"
                                        :class="form.errors.uraian_pekerjaan ? 'border-destructive' : ''"
                                        @focus="ukOpen = true"
                                        @blur="blurUk"
                                    />
                                    <div
                                        v-if="ukOpen && selectedAktivitasId"
                                        class="absolute z-50 mt-1 w-full rounded-md border bg-background shadow-lg max-h-48 overflow-y-auto"
                                    >
                                        <p v-if="!filteredUraianOptions.length" class="p-2 text-center text-xs text-muted-foreground">Tidak ada hasil</p>
                                        <button
                                            v-for="u in filteredUraianOptions"
                                            :key="u.id"
                                            type="button"
                                            class="w-full px-3 py-2 text-left text-sm hover:bg-muted/60 transition-colors"
                                            :class="form.uraian_kegiatan_id === u.id ? 'bg-primary/10 text-primary font-medium' : ''"
                                            @mousedown.prevent="selectUraian(u)"
                                        >{{ u.uraian_kegiatan }}</button>
                                    </div>
                                </div>
                                <p v-if="form.errors.uraian_pekerjaan" class="text-xs text-destructive">{{ form.errors.uraian_pekerjaan }}</p>
                            </template>
                            <div v-else class="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm">
                                {{ selectedItem?.uraian_kegiatan?.uraian_kegiatan || '-' }}
                            </div>
                        </div>
                    </div>

                    <!-- ③ Nominal Kontrak (desimal) + HPS -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label>Nominal Kontrak (Rp)</Label>
                            <input
                                v-model.number="nominalKontrak"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>HPS (Rp)</Label>
                            <div class="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm">
                                <span :class="selectedUraian ? 'text-foreground' : 'text-muted-foreground'">
                                    {{ selectedUraian ? formatRupiah(selectedUraian.anggaran_hps) : 'Otomatis dari uraian' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Persentase -->
                    <div v-if="selectedUraian" class="grid gap-1.5">
                        <Label>Persentase (Nominal / {{ persentaseBase?.label ?? 'HPS' }})</Label>
                        <div class="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm">
                            <span v-if="persentase" class="font-semibold text-primary">{{ persentase }}%</span>
                            <span v-else-if="!persentaseBase" class="text-muted-foreground">HPS & RAB belum diisi</span>
                            <span v-else class="text-muted-foreground">Isi Nominal Kontrak untuk menghitung</span>
                        </div>
                    </div>

                    <!-- ④ Waktu Pelaksanaan -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label>Tanggal Mulai</Label>
                            <input
                                v-model="form.tanggal_mulai"
                                type="date"
                                :min="yearMin"
                                :max="yearMax"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Tanggal Akhir</Label>
                            <input
                                v-model="form.tanggal_akhir"
                                type="date"
                                :min="form.tanggal_mulai || yearMin"
                                :max="yearMax"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                    </div>

                    <!-- Info durasi setelah tanggal dipilih -->
                    <div v-if="formDurasi" class="flex items-center gap-4 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                        <span>
                            <span class="font-semibold text-foreground">{{ formDurasi.calDays }}</span> hari kalender
                        </span>
                        <span class="text-muted-foreground/40">|</span>
                        <span>
                            <span class="font-semibold text-foreground">{{ formDurasi.workDays }}</span> hari kerja
                            <span class="ml-1 text-muted-foreground/60">(Senin–Jumat)</span>
                        </span>
                    </div>

                    <!-- ⑥ Dokumen Kontrak -->
                    <div class="rounded-md border p-3 grid gap-3">
                        <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Dokumen Kontrak</p>

                        <!-- Berkas yang sudah ada (mode edit) -->
                        <div v-if="modalType === 'edit' && selectedItem?.dokumen_url && !form.remove_dokumen" class="flex flex-col gap-1.5">
                            <Label>Dokumen Saat Ini</Label>
                            <div class="flex items-center gap-2 rounded-md border px-3 py-2 text-xs">
                                <Paperclip class="size-3.5 shrink-0 text-muted-foreground" />
                                <a
                                    :href="selectedItem.dokumen_url"
                                    target="_blank"
                                    class="flex-1 truncate text-primary hover:underline"
                                    :title="selectedItem.dokumen_name ?? 'Lihat dokumen'"
                                >{{ selectedItem.dokumen_name ?? 'Dokumen' }}</a>
                                <button
                                    type="button"
                                    class="shrink-0 rounded p-0.5 hover:bg-muted"
                                    title="Hapus dokumen"
                                    @click="form.remove_dokumen = true"
                                >
                                    <X class="size-3.5 text-red-400" />
                                </button>
                            </div>
                        </div>
                        <div v-else-if="modalType === 'edit' && selectedItem?.dokumen_url && form.remove_dokumen" class="rounded-md border border-dashed border-red-300 px-3 py-2 text-xs text-red-400">
                            Dokumen akan dihapus saat disimpan.
                            <button type="button" class="ml-2 underline" @click="form.remove_dokumen = false">Batalkan</button>
                        </div>

                        <!-- Upload baru -->
                        <div class="flex flex-col gap-1.5">
                            <Label>
                                {{ modalType === 'edit' && selectedItem?.dokumen_url ? 'Ganti Dokumen (opsional)' : 'Upload Dokumen (opsional)' }}
                            </Label>
                            <input
                                type="file"
                                accept=".pdf"
                                class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-muted/80"
                                @change="(e) => {
                                    form.dokumen = (e.target as HTMLInputElement).files?.[0] ?? null;
                                    if (form.dokumen) form.remove_dokumen = false;
                                }"
                            />
                            <p class="text-[11px] text-muted-foreground">PDF saja – maks 5 MB</p>
                            <p v-if="form.errors.dokumen" class="text-xs text-destructive">{{ form.errors.dokumen }}</p>
                        </div>
                    </div>

                    <!-- ⑦ Data Vendor — input manual -->
                    <div class="rounded-md border p-3 grid gap-3">
                        <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Data Vendor</p>

                        <!-- Jenis Vendor -->
                        <div class="grid gap-1.5">
                            <Label>Jenis Vendor</Label>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="radio" value="PT" v-model="form.jenis_vendor" class="accent-primary" />
                                    <span class="text-sm">PT</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="radio" value="CV" v-model="form.jenis_vendor" class="accent-primary" />
                                    <span class="text-sm">CV</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="radio" value="Pribadi" v-model="form.jenis_vendor" class="accent-primary" />
                                    <span class="text-sm">Pribadi (Perorangan)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Nama Vendor — hidden for Pribadi -->
                        <div v-if="form.jenis_vendor !== 'Pribadi'" class="grid gap-1.5">
                            <Label>Nama Vendor</Label>
                            <div class="relative">
                                <Input
                                    v-model="form.nama_vendor"
                                    placeholder="Masukkan nama vendor"
                                    autocomplete="off"
                                    @focus="vendorDropdownOpen = true"
                                    @blur="blurVendorInput"
                                />
                                <div
                                    v-if="vendorDropdownOpen && filteredVendorSuggestions.length"
                                    class="absolute z-50 mt-1 w-full rounded-md border bg-background shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <button
                                        v-for="v in filteredVendorSuggestions"
                                        :key="v.id"
                                        type="button"
                                        class="w-full px-3 py-2 text-left text-sm hover:bg-muted/60 transition-colors"
                                        @mousedown.prevent="selectVendorSuggestion(v)"
                                    >
                                        <span class="font-medium">{{ v.nama_vendor }}</span>
                                        <span v-if="v.direktur" class="ml-2 text-xs text-muted-foreground">— {{ v.direktur }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Direktur + No HP Direktur — hidden for Pribadi -->
                        <div v-if="form.jenis_vendor !== 'Pribadi'" class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label>Direktur</Label>
                                <Input v-model="form.direktur" placeholder="Nama direktur" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>No HP Direktur</Label>
                                <input
                                    :value="form.no_hp"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="20"
                                    placeholder="Contoh: 081234567890"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    @input="(e: Event) => form.no_hp = (e.target as HTMLInputElement).value.replace(/\D/g, '')"
                                />
                            </div>
                        </div>

                        <!-- Pelaksana + No HP Pelaksana -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label>Pelaksana</Label>
                                <Input v-model="form.pelaksana" placeholder="Nama pelaksana" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>No HP Pelaksana</Label>
                                <input
                                    :value="form.no_hp_pelaksana"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="12"
                                    placeholder="Contoh: 081234567890"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    :class="form.no_hp_pelaksana && (form.no_hp_pelaksana.length < 10 || form.no_hp_pelaksana.length > 12) ? 'border-destructive' : ''"
                                    @input="(e: Event) => form.no_hp_pelaksana = (e.target as HTMLInputElement).value.replace(/\D/g, '')"
                                />
                                <p v-if="form.no_hp_pelaksana && (form.no_hp_pelaksana.length < 10 || form.no_hp_pelaksana.length > 12)" class="text-xs text-destructive">
                                    Panjang tidak sesuai ({{ form.no_hp_pelaksana.length }} digit, harus 10–12 digit)
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeModal">Batal</Button>
                    <Button
                        :disabled="form.processing"
                        @click="modalType === 'add' ? submitAdd() : submitEdit()"
                    >
                        {{ form.processing ? 'Menyimpan...' : modalType === 'add' ? 'Simpan' : 'Update' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Modal View Detail ── -->
        <Dialog :open="modalType === 'view'" @update:open="(v) => !v && closeModal()">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle class="flex flex-wrap items-center gap-2">
                        <span class="font-mono">{{ selectedItem?.no_kontrak }}</span>
                        <span
                            v-if="viewStatus"
                            class="rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                            :class="viewStatus.cls"
                        >{{ viewStatus.text }}</span>
                    </DialogTitle>
                    <p v-if="selectedItem?.uraian_pekerjaan" class="mt-0.5 text-xs text-muted-foreground">
                        <span v-if="selectedItem.uraian_kegiatan?.aktivitas?.jenis_kegiatan" class="font-medium">
                            {{ selectedItem.uraian_kegiatan.aktivitas.jenis_kegiatan }} —
                        </span>
                        {{ selectedItem.uraian_pekerjaan }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">Tgl Kontrak: {{ formatDate(selectedItem?.tanggal_kontrak ?? null) }}</p>
                </DialogHeader>

                <div v-if="selectedItem" class="flex max-h-[65vh] flex-col gap-3 overflow-y-auto pr-0.5 py-1">

                    <!-- ── Keuangan ── -->
                    <div class="rounded-lg border p-4">
                        <p class="mb-3 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Keuangan</p>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <p class="text-[11px] text-muted-foreground">{{ viewHpsOrRab?.label ?? 'HPS' }}</p>
                                <p class="font-medium">{{ viewHpsOrRab ? formatRupiah(viewHpsOrRab.value) : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] text-muted-foreground">Nilai Kontrak</p>
                                <p class="font-semibold text-primary">{{ formatRupiah(selectedItem.nominal_kontrak) }}</p>
                            </div>
                        </div>
                        <!-- Progress serapan -->
                        <div v-if="viewPersentase !== null" class="mt-4">
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">Serapan (Nominal / {{ viewHpsOrRab?.label }})</span>
                                <span
                                    class="font-bold"
                                    :class="
                                        viewPersentase >= 90 ? 'text-green-600 dark:text-green-400' :
                                        viewPersentase >= 70 ? 'text-yellow-600 dark:text-yellow-400' :
                                        'text-red-500 dark:text-red-400'
                                    "
                                >{{ viewPersentase }}%</span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="
                                        viewPersentase >= 90 ? 'bg-green-500' :
                                        viewPersentase >= 70 ? 'bg-yellow-500' : 'bg-red-400'
                                    "
                                    :style="{ width: `${Math.min(viewPersentase, 100)}%` }"
                                />
                            </div>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                <template v-if="viewPersentase >= 100">Realisasi melebihi pagu</template>
                                <template v-else-if="viewPersentase >= 90">Serapan sangat baik</template>
                                <template v-else-if="viewPersentase >= 70">Serapan cukup baik</template>
                                <template v-else>Serapan masih rendah</template>
                            </p>
                        </div>
                        <p v-else class="mt-2 text-xs text-muted-foreground italic">
                            {{ !viewHpsOrRab ? 'HPS/RAB belum diisi — persentase tidak dapat dihitung' : 'Nominal kontrak belum diisi' }}
                        </p>
                    </div>

                    <!-- ── Waktu Pelaksanaan ── -->
                    <div v-if="selectedItem.tanggal_mulai || selectedItem.tanggal_akhir" class="rounded-lg border p-4">
                        <p class="mb-3 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Waktu Pelaksanaan</p>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <p class="text-[11px] text-muted-foreground">Tanggal Mulai</p>
                                <p class="font-medium">{{ formatDate(selectedItem.tanggal_mulai) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] text-muted-foreground">Tanggal Akhir</p>
                                <p class="font-medium">{{ formatDate(selectedItem.tanggal_akhir) }}</p>
                            </div>
                        </div>
                        <!-- Progress waktu -->
                        <div v-if="viewTimeElapsed !== null" class="mt-4">
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">Progres Waktu</span>
                                <span
                                    v-if="viewDaysLeft !== null"
                                    class="font-medium"
                                    :class="viewDaysLeft < 0 ? 'text-red-500' : viewDaysLeft <= 14 ? 'text-yellow-600' : 'text-muted-foreground'"
                                >
                                    <template v-if="viewDaysLeft > 0">{{ viewDaysLeft }} hari lagi</template>
                                    <template v-else-if="viewDaysLeft === 0">Berakhir hari ini</template>
                                    <template v-else>Lewat {{ Math.abs(viewDaysLeft) }} hari</template>
                                </span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="viewDaysLeft !== null && viewDaysLeft < 0 ? 'bg-red-400' : 'bg-blue-400'"
                                    :style="{ width: `${viewTimeElapsed}%` }"
                                />
                            </div>
                            <p class="mt-1 text-[10px] text-muted-foreground">{{ viewTimeElapsed }}% durasi kontrak telah berjalan</p>
                        </div>
                    </div>

                    <!-- ── Dokumen Kontrak ── -->
                    <div v-if="selectedItem.dokumen_url" class="rounded-lg border p-4">
                        <p class="mb-3 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Dokumen Kontrak</p>
                        <a
                            :href="selectedItem.dokumen_url"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm text-primary hover:bg-muted transition-colors"
                            :title="selectedItem.dokumen_name ?? 'Lihat dokumen'"
                        >
                            <Paperclip class="size-4 shrink-0" />
                            <span class="truncate">{{ selectedItem.dokumen_name ?? 'Dokumen Kontrak' }}</span>
                        </a>
                    </div>

                    <!-- ── Vendor & Pelaksana ── -->
                    <div class="rounded-lg border p-4">
                        <p class="mb-3 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Vendor &amp; Pelaksana</p>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <!-- Nama Vendor — hanya untuk PT/CV -->
                            <div v-if="selectedItem.vendor?.jenis_vendor !== 'Pribadi'" class="col-span-2">
                                <p class="text-[11px] text-muted-foreground">Nama Vendor</p>
                                <p class="font-medium">{{ namaVendor(selectedItem) }}</p>
                            </div>
                            <!-- Direktur + No HP — hanya untuk PT/CV -->
                            <div v-if="selectedItem.vendor?.jenis_vendor !== 'Pribadi'">
                                <p class="text-[11px] text-muted-foreground">Direktur</p>
                                <p>{{ selectedItem.vendor?.direktur || '-' }}</p>
                            </div>
                            <div v-if="selectedItem.vendor?.jenis_vendor !== 'Pribadi'">
                                <p class="text-[11px] text-muted-foreground">No HP Direktur</p>
                                <p>{{ selectedItem.vendor?.no_hp || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] text-muted-foreground">Pelaksana</p>
                                <p>{{ selectedItem.pelaksana || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] text-muted-foreground">No HP Pelaksana</p>
                                <p>{{ selectedItem.no_hp_pelaksana || '-' }}</p>
                            </div>
                        </div>
                    </div>

                </div>

                <DialogFooter>
                    <Button v-if="props.canEdit" variant="outline" @click="openEdit(selectedItem!)">Edit</Button>
                    <Button @click="closeModal">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
