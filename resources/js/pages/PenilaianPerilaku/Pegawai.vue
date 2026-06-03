<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Download, Eye, Pencil } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import penilaianPerilaku from '@/routes/penilaian-perilaku';
import type { Team } from '@/types';

interface PegawaiItem {
    id: number;
    name: string;
    email: string;
    role: string;
    status_pegawai: string | null;
    kode_unit: string | null;
    unit_nama: string;
    penilaian_id: number | null;
    status_penilaian: string;
}

interface Penilaian {
    id: number;
    user_id: number;
    penilai_id: number;
    periode: string;
    berorientasi_pelayanan: string | null;
    akuntabel: string | null;
    kompeten: string | null;
    harmonis: string | null;
    loyal: string | null;
    adaptif: string | null;
    kolaboratif: string | null;
    status: string;
    penilai?: { name: string };
}

const props = defineProps<{
    pegawaiList: PegawaiItem[];
    periode: string;
    statusFilter: string;
    penilaianBelumAktif?: boolean;
    isAdmin?: boolean;
}>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: '/' },
            { title: 'Penilaian Perilaku', href: layoutProps.currentTeam ? penilaianPerilaku.home(layoutProps.currentTeam.slug).url : '/' },
            { title: 'Penilaian Perilaku Pegawai', href: '#' },
        ],
    }),
});

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team);

const roleLabels: Record<string, string> = {
    admin_mutu: 'Admin Mutu',
    kepala_unit: 'Kepala Unit',
    staf: 'Staf',
};

const unsurPerilaku = [
    { key: 'berorientasi_pelayanan', label: 'Berorientasi Pelayanan', pedoman: 'Ramah, cekatan, solutif, dan dapat diandalkan' },
    { key: 'akuntabel', label: 'Akuntabel', pedoman: 'Tidak menyalahgunakan kewenangan jabatan' },
    { key: 'kompeten', label: 'Kompeten', pedoman: 'Melaksanakan tugas dengan kualitas terbaik' },
    { key: 'harmonis', label: 'Harmonis', pedoman: 'Suka menolong orang lain' },
    { key: 'loyal', label: 'Loyal', pedoman: 'Memegang teguh ideologi Pancasila, UUD 1945, NKRI serta pemerintahan yang sah' },
    { key: 'adaptif', label: 'Adaptif', pedoman: 'Bertindak Proaktif' },
    { key: 'kolaboratif', label: 'Kolaboratif', pedoman: 'Memberi kesempatan kepada berbagai pihak untuk berkontribusi' },
];

const nilaiLabels: Record<string, string> = {
    di_atas_ekspektasi: 'Di Atas Ekspektasi',
    sesuai_ekspektasi: 'Sesuai Ekspektasi',
    di_bawah_ekspektasi: 'Di Bawah Ekspektasi',
};

const nilaiColors: Record<string, string> = {
    di_atas_ekspektasi: 'text-green-600 dark:text-green-400',
    sesuai_ekspektasi: 'text-blue-600 dark:text-blue-400',
    di_bawah_ekspektasi: 'text-red-600 dark:text-red-400',
};

const konversiNilai: Record<string, number> = {
    di_atas_ekspektasi: 3,
    sesuai_ekspektasi: 2,
    di_bawah_ekspektasi: 1,
};

const viewRataRata = computed(() => {
    if (!viewPenilaian.value) return null;
    let total = 0;
    for (const unsur of unsurPerilaku) {
        total += konversiNilai[(viewPenilaian.value as any)[unsur.key]] ?? 0;
    }
    return Math.round((total / 7) * 100) / 100;
});

const viewKeterangan = computed(() => {
    const avg = viewRataRata.value;
    if (avg === null) return '-';
    if (avg >= 2.51) return 'Di Atas Ekspektasi';
    if (avg >= 1.5) return 'Sesuai Ekspektasi';
    return 'Di Bawah Ekspektasi';
});

const viewKeteranganColor = computed(() => {
    const avg = viewRataRata.value;
    if (avg === null) return '';
    if (avg >= 2.51) return 'text-green-600 dark:text-green-400';
    if (avg >= 1.5) return 'text-blue-600 dark:text-blue-400';
    return 'text-red-600 dark:text-red-400';
});

const flashSuccess = computed(() => (page.props as any).flash?.success || '');
const flashError = computed(() => (page.props as any).flash?.error || '');

const filterPeriode = ref(props.periode);
const filterStatus = ref(props.statusFilter);

const periodeOptions = computed(() => {
    const options: { value: string; label: string }[] = [];
    const namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const now = new Date();
    for (let i = 0; i < 12; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        options.push({ value: `${y}-${m}`, label: `${namaBulan[d.getMonth()]} ${y}` });
    }
    return options;
});

function tampilkan() {
    router.get(
        penilaianPerilaku.pegawai(currentTeam.value.slug).url,
        { periode: filterPeriode.value, status: filterStatus.value },
        { preserveState: true, preserveScroll: true },
    );
}

const exportUrl = computed(() => {
    if (!currentTeam.value) return '#';
    return penilaianPerilaku.pegawai.export(currentTeam.value.slug, { query: { periode: filterPeriode.value } }).url;
});

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const filteredList = computed(() => {
    if (!searchQuery.value) return props.pegawaiList;
    const q = searchQuery.value.toLowerCase();
    return props.pegawaiList.filter(
        (p) => p.name.toLowerCase().includes(q) || p.unit_nama.toLowerCase().includes(q) || (roleLabels[p.role] || p.role).toLowerCase().includes(q),
    );
});

const totalPages = computed(() => Math.ceil(filteredList.value.length / itemsPerPage));

const paginatedList = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredList.value.slice(start, start + itemsPerPage);
});

function goToPage(p: number) {
    if (p >= 1 && p <= totalPages.value) currentPage.value = p;
}

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    const pages: (number | '...')[] = [];

    if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i);
        return pages;
    }

    pages.push(1);
    if (current > 3) pages.push('...');
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let i = start; i <= end; i++) pages.push(i);
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
});

watch(searchQuery, () => {
    currentPage.value = 1;
});

const showView = ref(false);
const viewPegawai = ref<PegawaiItem | null>(null);
const viewPenilaian = ref<Penilaian | null>(null);
const viewLoading = ref(false);

const showEdit = ref(false);
const editPegawai = ref<PegawaiItem | null>(null);
const formEdit = ref<Record<string, string>>({
    berorientasi_pelayanan: '',
    akuntabel: '',
    kompeten: '',
    harmonis: '',
    loyal: '',
    adaptif: '',
    kolaboratif: '',
});
const editLoading = ref(false);
const isSubmitting = ref(false);

function openView(pegawai: PegawaiItem) {
    viewPegawai.value = pegawai;
    viewPenilaian.value = null;
    viewLoading.value = true;
    showView.value = true;

    fetch(penilaianPerilaku.pegawai.show(currentTeam.value.slug).url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
        body: JSON.stringify({ user_id: pegawai.id, periode: filterPeriode.value }),
    })
        .then((r) => r.json())
        .then((data) => {
            viewPenilaian.value = data.penilaian;
            viewLoading.value = false;
        })
        .catch(() => {
            viewLoading.value = false;
        });
}

function openEdit(pegawai: PegawaiItem) {
    editPegawai.value = pegawai;
    editLoading.value = true;
    showEdit.value = true;

    formEdit.value = {
        berorientasi_pelayanan: '',
        akuntabel: '',
        kompeten: '',
        harmonis: '',
        loyal: '',
        adaptif: '',
        kolaboratif: '',
    };

    fetch(penilaianPerilaku.pegawai.show(currentTeam.value.slug).url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
        body: JSON.stringify({ user_id: pegawai.id, periode: filterPeriode.value }),
    })
        .then((r) => r.json())
        .then((data) => {
            if (data.penilaian) {
                const p = data.penilaian;
                formEdit.value = {
                    berorientasi_pelayanan: p.berorientasi_pelayanan || '',
                    akuntabel: p.akuntabel || '',
                    kompeten: p.kompeten || '',
                    harmonis: p.harmonis || '',
                    loyal: p.loyal || '',
                    adaptif: p.adaptif || '',
                    kolaboratif: p.kolaboratif || '',
                };
            }
            editLoading.value = false;
        })
        .catch(() => {
            editLoading.value = false;
        });
}

function submitPenilaian() {
    if (isSubmitting.value || !editPegawai.value) return;

    const allFilled = unsurPerilaku.every((u) => formEdit.value[u.key]);
    if (!allFilled) {
        alert('Semua unsur perilaku harus dinilai!');
        return;
    }

    isSubmitting.value = true;

    const penilaianId = editPegawai.value.penilaian_id;

    if (penilaianId) {
        router.put(penilaianPerilaku.pegawai.update(currentTeam.value.slug, penilaianId).url, formEdit.value, {
            preserveScroll: true,
            onSuccess: () => {
                showEdit.value = false;
                isSubmitting.value = false;
            },
            onError: () => {
                isSubmitting.value = false;
            },
        });
    } else {
        router.post(
            penilaianPerilaku.pegawai.store(currentTeam.value.slug).url,
            { user_id: editPegawai.value.id, periode: filterPeriode.value, ...formEdit.value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showEdit.value = false;
                    isSubmitting.value = false;
                },
                onError: () => {
                    isSubmitting.value = false;
                },
            },
        );
    }
}
</script>

<template>
    <Head title="Penilaian Perilaku Pegawai" />
    <div class="flex h-full flex-1 flex-col gap-4 p-4 xl:p-6">

        <!-- Flash Messages -->
        <div v-if="flashSuccess" class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ flashError }}
        </div>

        <h2 class="text-xl font-bold dark:text-gray-100">Penilaian Perilaku Pegawai</h2>
        <p class="-mt-3 text-sm text-gray-500 dark:text-gray-400">Daftar pegawai yang dibawah struktur</p>

        <!-- Penilaian Belum Aktif -->
        <div v-if="penilaianBelumAktif" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-600 dark:bg-amber-900/20 dark:text-amber-300">
            <p class="font-semibold">Penilaian belum diaktifkan</p>
            <p class="mt-1">Fitur penilaian perilaku pegawai belum diaktifkan oleh admin. Silakan hubungi admin untuk mengaktifkan penilaian.</p>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3 flex-wrap">
            <label class="text-sm">
                <span class="mb-1 block font-medium dark:text-gray-200">Pilih Tahun Bulan</span>
                <select
                    v-model="filterPeriode"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                    <option v-for="opt in periodeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </label>
            <label v-if="!penilaianBelumAktif" class="text-sm">
                <span class="mb-1 block font-medium dark:text-gray-200">Pilih Status</span>
                <select
                    v-model="filterStatus"
                    @change="tampilkan"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                    <option value="semua">Semua</option>
                    <option value="selesai">Selesai</option>
                    <option value="belum_dinilai">Belum Dinilai</option>
                </select>
            </label>
            <button @click="tampilkan" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 shadow-md transition-colors">
                Tampilkan
            </button>
            <a
                v-if="isAdmin"
                :href="exportUrl"
                class="flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 shadow-md transition-colors"
                title="Download Excel"
            >
                <Download :size="15" />
                Download Excel
            </a>
            <div class="sm:ml-auto">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Pencarian..."
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-auto max-h-[60svh] border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-12">#</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Nama</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Jabatan</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Status Pegawai</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Status Penilaian</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="(item, index) in paginatedList" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ item.name }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ roleLabels[item.role] || item.role }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ item.status_pegawai || '-' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="item.status_penilaian === 'selesai'
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                            >
                                {{ item.status_penilaian === 'selesai' ? 'Selesai' : 'Belum Dinilai' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button @click="openView(item)" class="rounded p-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30" title="Lihat">
                                    <Eye :size="16" />
                                </button>
                                <button v-if="!penilaianBelumAktif" @click="openEdit(item)" class="rounded p-1.5 text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/30" title="Edit Penilaian">
                                    <Pencil :size="16" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="paginatedList.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            {{ searchQuery ? 'Tidak ada data yang cocok' : 'Belum ada data pegawai' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Menampilkan {{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredList.length) }} dari {{ filteredList.length }} data
            </p>
            <div class="flex items-center gap-1">
                <button @click="goToPage(1)" :disabled="currentPage === 1" class="rounded px-2.5 py-1.5 text-sm border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200" title="Halaman pertama">&laquo;</button>
                <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="rounded px-2.5 py-1.5 text-sm border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200" title="Sebelumnya">&lsaquo;</button>
                <template v-for="p in visiblePages" :key="String(p) + '_' + (p === '...' ? Math.random() : '')">
                    <span v-if="p === '...'" class="px-2 py-1.5 text-sm text-gray-400 select-none">…</span>
                    <button v-else @click="goToPage(p as number)" class="rounded px-3 py-1.5 text-sm border transition-colors min-w-[2rem]" :class="p === currentPage ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200'">{{ p }}</button>
                </template>
                <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" class="rounded px-2.5 py-1.5 text-sm border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200" title="Selanjutnya">&rsaquo;</button>
                <button @click="goToPage(totalPages)" :disabled="currentPage === totalPages" class="rounded px-2.5 py-1.5 text-sm border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200" title="Halaman terakhir">&raquo;</button>
            </div>
        </div>
    </div>

    <!-- Modal View Penilaian -->
    <Teleport to="body">
        <div v-if="showView" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showView = false">
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold dark:text-gray-100">Detail Penilaian</h4>
                    <button @click="showView = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div v-if="viewPegawai" class="mb-4 space-y-2">
                    <div class="grid grid-cols-4 gap-2 text-sm">
                        <span class="font-medium text-gray-600 dark:text-gray-400">Nama</span>
                        <span class="col-span-3 text-gray-900 dark:text-gray-100">{{ viewPegawai.name }}</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-sm">
                        <span class="font-medium text-gray-600 dark:text-gray-400">Jabatan</span>
                        <span class="col-span-3 text-gray-900 dark:text-gray-100">{{ roleLabels[viewPegawai.role] || viewPegawai.role }}</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-sm">
                        <span class="font-medium text-gray-600 dark:text-gray-400">Status Pegawai</span>
                        <span class="col-span-3 text-gray-900 dark:text-gray-100">{{ viewPegawai.status_pegawai || '-' }}</span>
                    </div>
                </div>

                <div v-if="viewLoading" class="text-center py-8 text-gray-500">Memuat data...</div>

                <div v-else-if="viewPenilaian">
                    <div class="overflow-auto max-h-[60svh] border border-gray-200 dark:border-gray-700 rounded-lg">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-10">No</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Unsur Perilaku</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Pedoman Perilaku</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Penilaian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(unsur, idx) in unsurPerilaku" :key="unsur.key">
                                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ idx + 1 }}</td>
                                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100 font-medium">{{ unsur.label }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ unsur.pedoman }}</td>
                                    <td class="px-3 py-2 font-medium" :class="nilaiColors[(viewPenilaian as any)[unsur.key]] || 'text-gray-400'">
                                        {{ nilaiLabels[(viewPenilaian as any)[unsur.key]] || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nilai Rata-Rata</p>
                                <p class="mt-1 text-2xl font-bold" :class="viewKeteranganColor">{{ viewRataRata }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Keterangan</p>
                                <p class="mt-1 text-lg font-semibold" :class="viewKeteranganColor">{{ viewKeterangan }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">Belum ada penilaian untuk periode ini</div>

                <div class="mt-6 flex justify-end">
                    <button @click="showView = false" class="rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md">Tutup</button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal Edit/Tambah Penilaian -->
    <Teleport to="body">
        <div v-if="showEdit" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showEdit = false">
            <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold dark:text-gray-100">Form Penilaian</h4>
                    <button @click="showEdit = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div v-if="editPegawai" class="mb-4 space-y-2">
                    <div class="grid grid-cols-4 gap-2 text-sm">
                        <span class="font-medium text-gray-600 dark:text-gray-400">Nama</span>
                        <span class="col-span-3 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded text-gray-900 dark:text-gray-100">{{ editPegawai.name }}</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-sm">
                        <span class="font-medium text-gray-600 dark:text-gray-400">Status Pegawai</span>
                        <span class="col-span-3 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded text-gray-900 dark:text-gray-100">{{ editPegawai.status_pegawai || '-' }}</span>
                    </div>
                </div>

                <div v-if="editLoading" class="text-center py-8 text-gray-500">Memuat data...</div>

                <div v-else class="overflow-auto max-h-[60svh] border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-10">No</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Unsur Perilaku</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Pedoman Perilaku</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Penilaian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(unsur, idx) in unsurPerilaku" :key="unsur.key">
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ idx + 1 }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100 font-medium">{{ unsur.label }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400 max-w-xs">{{ unsur.pedoman }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-col gap-1">
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input type="radio" :name="unsur.key" value="di_atas_ekspektasi" v-model="formEdit[unsur.key]" class="text-green-600 focus:ring-green-500" />
                                            <span class="text-green-600 dark:text-green-400 text-xs">Di Atas Ekspektasi</span>
                                        </label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input type="radio" :name="unsur.key" value="sesuai_ekspektasi" v-model="formEdit[unsur.key]" class="text-blue-600 focus:ring-blue-500" />
                                            <span class="text-blue-600 dark:text-blue-400 text-xs">Sesuai Ekspektasi</span>
                                        </label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input type="radio" :name="unsur.key" value="di_bawah_ekspektasi" v-model="formEdit[unsur.key]" class="text-red-600 focus:ring-red-500" />
                                            <span class="text-red-600 dark:text-red-400 text-xs">Di Bawah Ekspektasi</span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button @click="showEdit = false" class="rounded-lg border border-gray-300 px-5 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
                    <button @click="submitPenilaian" :disabled="isSubmitting" class="rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md disabled:opacity-50">
                        {{ isSubmitting ? 'Menyimpan...' : 'Simpan Penilaian' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
