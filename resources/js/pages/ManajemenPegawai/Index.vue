<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Download, Eye, Pencil, Plus, Save, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import manajemenPegawai from '@/routes/manajemen-pegawai';
import penilaianPerilaku from '@/routes/penilaian-perilaku';
import type { Team } from '@/types';

interface Unit {
    id: number;
    kode_unit: string;
    nama_unit: string;
    alias: string;
}

interface Pegawai {
    id: number;
    name: string;
    nip: string | null;
    role: string;
    status_pegawai: string | null;
    status_kerja: string | null;
    kode_unit: string | null;
    unit?: { kode_unit: string; nama_unit: string } | null;
}

const props = defineProps<{
    users: Pegawai[];
    units: Unit[];
}>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: '/' },
            { title: 'Penilaian Perilaku', href: layoutProps.currentTeam ? penilaianPerilaku.home(layoutProps.currentTeam.slug).url : '/' },
            { title: 'Manajemen Pegawai', href: '#' },
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

const jabatanOptions = [
    'PNS', 'CPNS', 'PPPK', 'PPPK Paruh Waktu',
    'Pegawai Blud (Tetap Non ASN)', 'PJLP', 'Mitra', 'Pegawai Lainnya Non ASN',
];

const statusKerjaOptions = ['Aktif', 'Resign', 'Pensiun', 'Mutasi'];

const statusKerjaBadge: Record<string, string> = {
    Aktif: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    Resign: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    Pensiun: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
    Mutasi: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
};

const flashSuccess = computed(() => (page.props as any).flash?.success || '');
const flashError = computed(() => (page.props as any).flash?.error || '');
const flashImportSuccess = computed<number | null>(() => (page.props as any).flash?.import_success ?? null);
const flashImportFailures = computed<string[]>(() => (page.props as any).flash?.import_failures ?? []);

/* ====== Search & Pagination ====== */
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const filteredUsers = computed(() => {
    if (!searchQuery.value) return props.users;
    const q = searchQuery.value.toLowerCase();
    return props.users.filter(
        (u) => u.name.toLowerCase().includes(q) || (roleLabels[u.role] || u.role).toLowerCase().includes(q),
    );
});

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / itemsPerPage));

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
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

/* ====== Modal State ====== */
const showView = ref(false);
const viewItem = ref<Pegawai | null>(null);

const showAdd = ref(false);
const formAdd = ref({ name: '', nip: '', password: '', role: '', status_pegawai: '', status_kerja: '', kode_unit: '' });
const addErrors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const showEdit = ref(false);
const editItem = ref<Pegawai | null>(null);
const formEdit = ref({ name: '', nip: '', password: '', role: '', status_pegawai: '', status_kerja: '', kode_unit: '' });
const editErrors = ref<Record<string, string>>({});

const showDelete = ref(false);
const deleteItem = ref<Pegawai | null>(null);

const activeTab = ref<'form' | 'excel'>('form');
const importFile = ref<File | null>(null);
const importErrors = ref<Record<string, string>>({});
const isImporting = ref(false);

const templateUrl = computed(() =>
    currentTeam.value ? manajemenPegawai.template(currentTeam.value.slug).url : '#',
);

/* ====== Modal Actions ====== */
function openView(user: Pegawai) {
    viewItem.value = user;
    showView.value = true;
}

function openAdd() {
    formAdd.value = { name: '', nip: '', password: '', role: '', status_pegawai: '', status_kerja: '', kode_unit: '' };
    addErrors.value = {};
    importFile.value = null;
    importErrors.value = {};
    activeTab.value = 'form';
    showAdd.value = true;
}

function openEdit(user: Pegawai) {
    editItem.value = user;
    formEdit.value = {
        name: user.name,
        nip: user.nip || '',
        password: '',
        role: user.role,
        status_pegawai: user.status_pegawai || '',
        status_kerja: user.status_kerja || '',
        kode_unit: user.kode_unit || '',
    };
    editErrors.value = {};
    showEdit.value = true;
}

function openDelete(user: Pegawai) {
    deleteItem.value = user;
    showDelete.value = true;
}

/* ====== CRUD ====== */
function submitAdd() {
    if (isSubmitting.value) return;
    addErrors.value = {};

    if (!formAdd.value.name) addErrors.value.name = 'Nama wajib diisi';
    if (!formAdd.value.password) addErrors.value.password = 'Password wajib diisi';
    if (!formAdd.value.role) addErrors.value.role = 'Peran wajib dipilih';

    if (Object.keys(addErrors.value).length > 0) return;

    isSubmitting.value = true;
    router.post(manajemenPegawai.store(currentTeam.value.slug).url, formAdd.value, {
        preserveScroll: true,
        onSuccess: () => {
            showAdd.value = false;
            isSubmitting.value = false;
        },
        onError: (errors) => {
            addErrors.value = errors as Record<string, string>;
            isSubmitting.value = false;
        },
    });
}

function submitEdit() {
    if (isSubmitting.value || !editItem.value) return;
    editErrors.value = {};

    if (!formEdit.value.name) editErrors.value.name = 'Nama wajib diisi';
    if (!formEdit.value.role) editErrors.value.role = 'Peran wajib dipilih';

    if (Object.keys(editErrors.value).length > 0) return;

    isSubmitting.value = true;
    router.put(manajemenPegawai.update({ current_team: currentTeam.value.slug, id: editItem.value.id }).url, formEdit.value, {
        preserveScroll: true,
        onSuccess: () => {
            showEdit.value = false;
            isSubmitting.value = false;
        },
        onError: (errors) => {
            editErrors.value = errors as Record<string, string>;
            isSubmitting.value = false;
        },
    });
}

function submitDelete() {
    if (isSubmitting.value || !deleteItem.value) return;
    isSubmitting.value = true;
    router.delete(manajemenPegawai.destroy({ current_team: currentTeam.value.slug, id: deleteItem.value.id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            showDelete.value = false;
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    importFile.value = target.files?.[0] ?? null;
}

function submitImport() {
    if (isImporting.value || !importFile.value) return;
    importErrors.value = {};
    isImporting.value = true;
    router.post(
        manajemenPegawai.import(currentTeam.value.slug).url,
        { file: importFile.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAdd.value = false;
                isImporting.value = false;
                importFile.value = null;
            },
            onError: (errors) => {
                importErrors.value = errors as Record<string, string>;
                isImporting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Manajemen Pegawai" />
    <div class="flex h-full flex-1 flex-col gap-4 p-4 xl:p-6">

        <!-- Flash Messages -->
        <div v-if="flashSuccess" class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ flashError }}
        </div>
        <div v-if="flashImportSuccess !== null" class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ flashImportSuccess }} pegawai berhasil diimpor dari Excel.
        </div>
        <div v-if="flashImportFailures.length > 0" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm dark:bg-red-900/20 dark:border-red-800">
            <p class="font-medium text-red-700 dark:text-red-300 mb-2">{{ flashImportFailures.length }} baris gagal diimpor:</p>
            <ul class="list-disc list-inside space-y-1 text-red-600 dark:text-red-400">
                <li v-for="failure in flashImportFailures" :key="failure">{{ failure }}</li>
            </ul>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <h2 class="text-xl font-bold dark:text-gray-100">Manajemen Pegawai</h2>
            <button @click="openAdd" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 shadow-md transition-colors">
                <Plus :size="16" />
                Tambah Pegawai
            </button>
        </div>

        <!-- Search -->
        <div class="flex items-center gap-2">
            <div class="relative w-full max-w-md">
                <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari nama atau peran..."
                    class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-auto max-h-[60svh] border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-12">No</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Nama</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">NIP</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Peran</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Jabatan</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Unit</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="(user, index) in paginatedUsers" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ user.name }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ user.nip || '-' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300': user.role === 'admin_mutu',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300': user.role === 'kepala_unit',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': user.role === 'staf',
                                }"
                            >
                                {{ roleLabels[user.role] || user.role }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ user.status_pegawai || '-' }}</td>
                        <td class="px-4 py-3">
                            <span v-if="user.status_kerja" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusKerjaBadge[user.status_kerja] ?? 'bg-gray-100 text-gray-700'">
                                {{ user.status_kerja }}
                            </span>
                            <span v-else class="text-gray-400 text-sm">-</span>
                        </td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100 text-xs">{{ user.unit ? user.unit.nama_unit : '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button @click="openView(user)" class="rounded p-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30" title="Lihat">
                                    <Eye :size="16" />
                                </button>
                                <button @click="openEdit(user)" class="rounded p-1.5 text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/30" title="Edit">
                                    <Pencil :size="16" />
                                </button>
                                <button @click="openDelete(user)" class="rounded p-1.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30" title="Hapus">
                                    <Trash2 :size="16" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="paginatedUsers.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            {{ searchQuery ? 'Tidak ada data yang cocok' : 'Belum ada data pegawai' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Menampilkan {{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }} dari {{ filteredUsers.length }} data
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

    <!-- Modal View -->
    <Teleport to="body">
        <div v-if="showView" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showView = false">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold dark:text-gray-100">Detail Pegawai</h4>
                    <button @click="showView = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <div v-if="viewItem" class="space-y-3">
                    <div v-for="([label, value]) in [
                        ['Nama', viewItem.name],
                        ['NIP', viewItem.nip || '-'],
                        ['Peran', roleLabels[viewItem.role] || viewItem.role],
                        ['Jabatan', viewItem.status_pegawai || '-'],
                        ['Status', viewItem.status_kerja || '-'],
                        ['Unit', viewItem.unit ? viewItem.unit.nama_unit : '-'],
                    ]" :key="label" class="grid grid-cols-3 gap-2 text-sm">
                        <span class="font-medium text-gray-600 dark:text-gray-400">{{ label }}</span>
                        <span class="col-span-2 dark:text-gray-100">{{ value }}</span>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button @click="showView = false" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md">
                        <X :size="15" /> Tutup
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal Add -->
    <Teleport to="body">
        <div v-if="showAdd" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showAdd = false">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl dark:bg-gray-900 max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 pt-6 pb-0">
                    <h4 class="text-lg font-semibold dark:text-gray-100">Tambah Pegawai</h4>
                    <button @click="showAdd = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-gray-200 dark:border-gray-700 px-6 mt-4">
                    <button
                        @click="activeTab = 'form'"
                        class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'form' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    >
                        <Plus :size="14" /> Isi Form
                    </button>
                    <button
                        @click="activeTab = 'excel'"
                        class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'excel' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    >
                        <Upload :size="14" /> Upload Excel
                    </button>
                </div>

                <!-- Tab: Isi Form -->
                <div v-if="activeTab === 'form'" class="overflow-y-auto px-6 py-4 space-y-4">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Nama <span class="text-red-500">*</span></span>
                        <input v-model="formAdd.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                        <span v-if="addErrors.name" class="text-xs text-red-500 mt-1 block">{{ addErrors.name }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">NIP</span>
                        <input v-model="formAdd.nip" type="text" placeholder="Nomor Induk Pegawai" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                        <span v-if="addErrors.nip" class="text-xs text-red-500 mt-1 block">{{ addErrors.nip }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Password <span class="text-red-500">*</span></span>
                        <input v-model="formAdd.password" type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                        <span v-if="addErrors.password" class="text-xs text-red-500 mt-1 block">{{ addErrors.password }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Peran <span class="text-red-500">*</span></span>
                        <select v-model="formAdd.role" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="" disabled>Pilih Peran...</option>
                            <option value="admin_mutu">Admin Mutu</option>
                            <option value="kepala_unit">Kepala Unit</option>
                            <option value="staf">Staf</option>
                        </select>
                        <span v-if="addErrors.role" class="text-xs text-red-500 mt-1 block">{{ addErrors.role }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Jabatan</span>
                        <select v-model="formAdd.status_pegawai" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">Pilih Jabatan...</option>
                            <option v-for="opt in jabatanOptions" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Status Pegawai</span>
                        <select v-model="formAdd.status_kerja" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">Pilih Status...</option>
                            <option v-for="opt in statusKerjaOptions" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Unit</span>
                        <select v-model="formAdd.kode_unit" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">Tidak ada unit</option>
                            <option v-for="unit in units" :key="unit.id" :value="unit.kode_unit">{{ unit.kode_unit }} - {{ unit.nama_unit }}</option>
                        </select>
                    </label>
                    <div class="flex justify-end gap-3 pt-2">
                        <button @click="showAdd = false" class="flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            <X :size="15" /> Batal
                        </button>
                        <button @click="submitAdd" :disabled="isSubmitting" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md disabled:opacity-50">
                            <Save :size="15" /> {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </div>

                <!-- Tab: Upload Excel -->
                <div v-else class="overflow-y-auto px-6 py-4 space-y-4">
                    <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300">
                        <p class="font-medium mb-1">Panduan Upload:</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>Download template, isi data sesuai format yang tersedia</li>
                            <li>Gunakan dropdown di Excel untuk Peran, Status, dan Unit Kerja</li>
                            <li>Kolom Password kosong akan menggunakan default: <strong>password</strong></li>
                            <li>Baris yang gagal akan ditampilkan setelah upload selesai</li>
                        </ul>
                    </div>
                    <a :href="templateUrl" class="flex items-center justify-center gap-2 rounded-lg border border-blue-300 bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/30 transition-colors">
                        <Download :size="16" />
                        Download Template Excel
                    </a>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">File Excel <span class="text-red-500">*</span></span>
                        <input
                            type="file"
                            accept=".xlsx,.xls"
                            @change="onFileChange"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                        <span v-if="importErrors.file" class="text-xs text-red-500 mt-1 block">{{ importErrors.file }}</span>
                    </label>
                    <div class="flex justify-end gap-3 pt-2">
                        <button @click="showAdd = false" class="flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            <X :size="15" /> Batal
                        </button>
                        <button @click="submitImport" :disabled="isImporting || !importFile" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md disabled:opacity-50">
                            <Upload :size="15" /> {{ isImporting ? 'Mengupload...' : 'Upload' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal Edit -->
    <Teleport to="body">
        <div v-if="showEdit" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showEdit = false">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold dark:text-gray-100">Edit Pegawai</h4>
                    <button @click="showEdit = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div class="space-y-4">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Nama <span class="text-red-500">*</span></span>
                        <input v-model="formEdit.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                        <span v-if="editErrors.name" class="text-xs text-red-500 mt-1">{{ editErrors.name }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">NIP</span>
                        <input v-model="formEdit.nip" type="text" placeholder="Nomor Induk Pegawai" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                        <span v-if="editErrors.nip" class="text-xs text-red-500 mt-1">{{ editErrors.nip }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Password <span class="text-gray-400 text-xs">(kosongkan jika tidak diubah)</span></span>
                        <input v-model="formEdit.password" type="password" placeholder="Kosongkan jika tidak diubah" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                        <span v-if="editErrors.password" class="text-xs text-red-500 mt-1">{{ editErrors.password }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Peran <span class="text-red-500">*</span></span>
                        <select v-model="formEdit.role" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="" disabled>Pilih Peran...</option>
                            <option value="admin_mutu">Admin Mutu</option>
                            <option value="kepala_unit">Kepala Unit</option>
                            <option value="staf">Staf</option>
                        </select>
                        <span v-if="editErrors.role" class="text-xs text-red-500 mt-1">{{ editErrors.role }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Jabatan</span>
                        <select v-model="formEdit.status_pegawai" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">Pilih Jabatan...</option>
                            <option v-for="opt in jabatanOptions" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Status Pegawai</span>
                        <select v-model="formEdit.status_kerja" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">Pilih Status...</option>
                            <option v-for="opt in statusKerjaOptions" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium dark:text-gray-200">Unit</span>
                        <select v-model="formEdit.kode_unit" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">Tidak ada unit</option>
                            <option v-for="unit in units" :key="unit.id" :value="unit.kode_unit">{{ unit.kode_unit }} - {{ unit.nama_unit }}</option>
                        </select>
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button @click="showEdit = false" class="flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                        <X :size="15" /> Batal
                    </button>
                    <button @click="submitEdit" :disabled="isSubmitting" class="flex items-center gap-2 rounded-lg bg-amber-600 px-5 py-2 text-white hover:bg-amber-700 shadow-md disabled:opacity-50">
                        <Save :size="15" /> {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal Delete -->
    <Teleport to="body">
        <div v-if="showDelete" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showDelete = false">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-red-600 dark:text-red-400">Hapus Pegawai</h4>
                    <button @click="showDelete = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300">Apakah Anda yakin ingin menghapus pegawai <strong>{{ deleteItem?.name }}</strong>?</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="showDelete = false" class="flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                        <X :size="15" /> Batal
                    </button>
                    <button @click="submitDelete" :disabled="isSubmitting" class="flex items-center gap-2 rounded-lg bg-red-600 px-5 py-2 text-white hover:bg-red-700 shadow-md disabled:opacity-50">
                        <Trash2 :size="15" /> {{ isSubmitting ? 'Menghapus...' : 'Hapus' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
