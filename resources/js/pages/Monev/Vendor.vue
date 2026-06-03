<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Download, Eye } from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Penilaian = {
    status: 'good' | 'consider' | 'bad' | 'nodata';
    label: string;
    note: string;
};

type Vendor = {
    id: number;
    jenis_vendor: 'PT' | 'CV' | 'Pribadi';
    nama_vendor: string;
    direktur: string | null;
    no_hp: string | null;
    total_kontrak: number;
    penilaian: Penilaian;
};

const props = defineProps<{
    vendor: Vendor[];
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug ?? '');
function monevRoute(path: string) { return `/${teamSlug.value}/monev${path}`; }

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Vendor', href: '#' }];

// ─── View modal ───────────────────────────────────────────────────────────────
const viewItem = ref<Vendor | null>(null);
function openView(item: Vendor) { viewItem.value = item; }
function closeView() { viewItem.value = null; }

// ─── Search ───────────────────────────────────────────────────────────────────
const search = ref('');
const filteredVendor = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.vendor;
    return props.vendor.filter(v =>
        v.nama_vendor.toLowerCase().includes(q) ||
        v.jenis_vendor.toLowerCase().includes(q) ||
        (v.direktur && v.direktur.toLowerCase().includes(q))
    );
});

// ─── Pagination ───────────────────────────────────────────────────────────────
const PAGE_SIZE = 10;
const currentPage = ref(1);
watch(filteredVendor, () => { currentPage.value = 1; });
const totalPages = computed(() => Math.max(1, Math.ceil(filteredVendor.value.length / PAGE_SIZE)));
const paginatedVendor = computed(() =>
    filteredVendor.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE)
);

// ─── Helpers ──────────────────────────────────────────────────────────────────
function namaLengkap(item: Vendor) {
    if (item.jenis_vendor === 'Pribadi') return item.nama_vendor;
    return `${item.jenis_vendor} ${item.nama_vendor}`;
}

function labelDirektur(jenis: 'PT' | 'CV' | 'Pribadi') {
    return jenis === 'Pribadi' ? 'Nama' : 'Direktur';
}

function labelNoHp(jenis: 'PT' | 'CV' | 'Pribadi') {
    return jenis === 'Pribadi' ? 'No HP' : 'No HP Direktur';
}

function jenisBadgeClass(jenis: string) {
    if (jenis === 'PT')      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    if (jenis === 'CV')      return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
}

function penilaianBadgeClass(status: string) {
    if (status === 'good')    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    if (status === 'consider') return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    if (status === 'bad')     return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    return 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400';
}
</script>

<template>
    <Head title="Vendor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Daftar Vendor</h1>
                <div class="flex items-center gap-2">
                    <Input
                        v-model="search"
                        placeholder="Cari vendor..."
                        class="h-8 w-52 text-sm"
                    />
                    <a :href="monevRoute('/vendor/export')" target="_blank">
                        <Button size="sm" variant="outline" type="button">
                            <Download class="mr-1.5 size-4" />
                            Download Excel
                        </Button>
                    </a>
                </div>
            </div>

            <!-- Info -->
            <p class="text-xs text-muted-foreground -mt-2">
                Data vendor terisi otomatis dari form Tambah / Edit Kontrak.
            </p>

            <!-- Tabel -->
            <div class="overflow-auto max-h-[60svh] rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-muted/60">
                        <tr>
                            <th class="w-10 border-b p-3 text-center font-semibold">No</th>
                            <th class="w-24 border-b p-3 text-left font-semibold">Jenis</th>
                            <th class="border-b p-3 text-left font-semibold">Nama Vendor</th>
                            <th class="w-20 border-b p-3 text-center font-semibold">Kontrak</th>
                            <th class="w-52 border-b p-3 text-center font-semibold">Penilaian</th>
                            <th class="w-16 border-b p-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, index) in paginatedVendor"
                            :key="item.id"
                            class="border-b transition-colors last:border-0 hover:bg-muted/30"
                        >
                            <td class="p-3 text-center text-muted-foreground">{{ (currentPage - 1) * PAGE_SIZE + index + 1 }}</td>
                            <td class="p-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="jenisBadgeClass(item.jenis_vendor)"
                                >
                                    {{ item.jenis_vendor }}
                                </span>
                            </td>
                            <td class="p-3 font-medium">{{ item.nama_vendor }}</td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                    {{ item.total_kontrak }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="penilaianBadgeClass(item.penilaian.status)"
                                    :title="item.penilaian.note"
                                >
                                    {{ item.penilaian.label }}
                                </span>
                                <p v-if="item.penilaian.note" class="mt-0.5 text-[10px] text-muted-foreground leading-tight">
                                    {{ item.penilaian.note }}
                                </p>
                            </td>
                            <td class="p-3 text-center">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-7"
                                    title="Lihat Detail"
                                    @click="openView(item)"
                                >
                                    <Eye class="size-3.5" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!paginatedVendor.length">
                            <td colspan="6" class="p-8 text-center text-muted-foreground">
                                {{ search ? 'Tidak ada vendor yang cocok.' : 'Belum ada data vendor. Tambahkan kontrak terlebih dahulu.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination v-model:currentPage="currentPage" :totalPages="totalPages" />
        </div>

        <!-- Modal View -->
        <Dialog :open="viewItem !== null" @update:open="(v) => !v && closeView()">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Detail Vendor</DialogTitle>
                </DialogHeader>

                <div v-if="viewItem" class="divide-y rounded-lg border text-sm">
                    <div class="grid grid-cols-5 gap-2 px-4 py-2.5">
                        <span class="col-span-2 font-medium text-muted-foreground">Jenis</span>
                        <span class="col-span-3">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="jenisBadgeClass(viewItem.jenis_vendor)"
                            >{{ viewItem.jenis_vendor }}</span>
                        </span>
                    </div>
                    <div class="grid grid-cols-5 gap-2 px-4 py-2.5">
                        <span class="col-span-2 font-medium text-muted-foreground">Nama Vendor</span>
                        <span class="col-span-3 font-medium">{{ namaLengkap(viewItem) }}</span>
                    </div>
                    <div class="grid grid-cols-5 gap-2 px-4 py-2.5">
                        <span class="col-span-2 font-medium text-muted-foreground">{{ labelDirektur(viewItem.jenis_vendor) }}</span>
                        <span class="col-span-3">{{ viewItem.direktur || '-' }}</span>
                    </div>
                    <div class="grid grid-cols-5 gap-2 px-4 py-2.5">
                        <span class="col-span-2 font-medium text-muted-foreground">{{ labelNoHp(viewItem.jenis_vendor) }}</span>
                        <span class="col-span-3">{{ viewItem.no_hp || '-' }}</span>
                    </div>
                    <div class="grid grid-cols-5 gap-2 px-4 py-2.5">
                        <span class="col-span-2 font-medium text-muted-foreground">Jumlah Kontrak</span>
                        <span class="col-span-3">{{ viewItem.total_kontrak }} kontrak</span>
                    </div>
                    <div class="grid grid-cols-5 gap-2 px-4 py-2.5">
                        <span class="col-span-2 font-medium text-muted-foreground">Penilaian</span>
                        <span class="col-span-3">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="penilaianBadgeClass(viewItem.penilaian.status)"
                            >{{ viewItem.penilaian.label }}</span>
                            <p class="mt-1 text-xs text-muted-foreground">{{ viewItem.penilaian.note }}</p>
                        </span>
                    </div>
                </div>

                <DialogFooter>
                    <Button @click="closeView">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
