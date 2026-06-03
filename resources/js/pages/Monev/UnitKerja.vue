<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pencil, Trash2, Plus, Eye, Building2, Layers } from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

// ── Types ─────────────────────────────────────────────────────────────────────
type InstansiRow = {
    id: number;
    nama_instansi: string;
    unit_kerja?: UnitKerjaRow[];
};

type UnitKerjaRow = {
    id: number;
    instansi_id: number;
    kode_unit_kerja: string | null;
    nama_unit_kerja: string;
    nama_atasan: string | null;
    nip: string | null;
    instansi?: InstansiRow;
};

const props = defineProps<{
    instansi: InstansiRow[];
    unitKerja: UnitKerjaRow[];
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug ?? '');
function monevRoute(path: string) { return `/${teamSlug.value}/monev${path}`; }

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Unit Kerja', href: '#' }];

// ── Pagination ────────────────────────────────────────────────────────────────
const PAGE_SIZE = 7;
const instansiPage = ref(1);
const totalInstansiPages = computed(() => Math.max(1, Math.ceil(props.instansi.length / PAGE_SIZE)));
const paginatedInstansi = computed(() =>
    props.instansi.slice((instansiPage.value - 1) * PAGE_SIZE, instansiPage.value * PAGE_SIZE)
);

const ukPage = ref(1);

// ── Search & Filter ───────────────────────────────────────────────────────────
const search          = ref('');
const filterInstansi  = ref<number | null>(null);

const filteredUnitKerja = computed(() => {
    let list = props.unitKerja;
    if (filterInstansi.value) {
        list = list.filter(u => u.instansi_id === filterInstansi.value);
    }
    const q = search.value.trim().toLowerCase();
    if (q) {
        list = list.filter(u =>
            u.nama_unit_kerja.toLowerCase().includes(q) ||
            (u.kode_unit_kerja && u.kode_unit_kerja.toLowerCase().includes(q)) ||
            (u.instansi?.nama_instansi.toLowerCase().includes(q))
        );
    }
    return list;
});

const totalUkPages = computed(() => Math.max(1, Math.ceil(filteredUnitKerja.value.length / PAGE_SIZE)));
const paginatedUnitKerja = computed(() =>
    filteredUnitKerja.value.slice((ukPage.value - 1) * PAGE_SIZE, ukPage.value * PAGE_SIZE)
);
watch(filteredUnitKerja, () => { ukPage.value = 1; });

// ── Instansi CRUD ─────────────────────────────────────────────────────────────
const instansiForm   = useForm({ nama_instansi: '' });
const editInstansi   = ref<InstansiRow | null>(null);
const deleteInstansiId = ref<number | null>(null);

function openAddInstansi() {
    editInstansi.value = null;
    instansiForm.reset();
    showInstansiModal.value = true;
}

function openEditInstansi(row: InstansiRow) {
    editInstansi.value          = row;
    instansiForm.nama_instansi  = row.nama_instansi;
    showInstansiModal.value     = true;
}

const showInstansiModal = ref(false);

function submitInstansi() {
    if (editInstansi.value) {
        instansiForm.put(monevRoute(`/instansi/${editInstansi.value.id}`), {
            onSuccess: () => { showInstansiModal.value = false; instansiForm.reset(); },
        });
    } else {
        instansiForm.post(monevRoute('/instansi'), {
            onSuccess: () => { showInstansiModal.value = false; instansiForm.reset(); },
        });
    }
}

function confirmDeleteInstansi() {
    if (!deleteInstansiId.value) return;
    router.delete(monevRoute(`/instansi/${deleteInstansiId.value}`), {
        onSuccess: () => { deleteInstansiId.value = null; },
    });
}

// ── Unit Kerja CRUD ───────────────────────────────────────────────────────────
const ukForm = useForm({
    instansi_id:     '' as string | number,
    kode_unit_kerja: '',
    nama_unit_kerja: '',
    nama_atasan:     '',
    nip:             '',
});

const editUK        = ref<UnitKerjaRow | null>(null);
const viewUK        = ref<UnitKerjaRow | null>(null);
const deleteUKId    = ref<number | null>(null);
const deleteUKLabel = ref('');
const showUKModal   = ref(false);

function openAddUK() {
    editUK.value = null;
    ukForm.reset();
    ukForm.instansi_id = filterInstansi.value ?? '';
    showUKModal.value  = true;
}

function openEditUK(row: UnitKerjaRow) {
    editUK.value             = row;
    ukForm.instansi_id       = row.instansi_id;
    ukForm.kode_unit_kerja   = row.kode_unit_kerja ?? '';
    ukForm.nama_unit_kerja   = row.nama_unit_kerja;
    ukForm.nama_atasan       = row.nama_atasan ?? '';
    ukForm.nip               = row.nip ?? '';
    showUKModal.value        = true;
}

function submitUK() {
    if (editUK.value) {
        ukForm.put(monevRoute(`/unit-kerja/${editUK.value.id}`), {
            onSuccess: () => { showUKModal.value = false; ukForm.reset(); },
        });
    } else {
        ukForm.post(monevRoute('/unit-kerja'), {
            onSuccess: () => { showUKModal.value = false; ukForm.reset(); },
        });
    }
}

function openDeleteUK(row: UnitKerjaRow) {
    deleteUKId.value    = row.id;
    deleteUKLabel.value = row.nama_unit_kerja;
}

function confirmDeleteUK() {
    if (!deleteUKId.value) return;
    router.delete(monevRoute(`/unit-kerja/${deleteUKId.value}`), {
        onSuccess: () => { deleteUKId.value = null; },
    });
}
</script>

<template>
    <Head title="Unit Kerja" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">

            <!-- ── Instansi Section ─────────────────────────────────────────── -->
            <div class="rounded-xl border">
                <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Building2 class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold">Instansi</h2>
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                            {{ instansi.length }}
                        </span>
                    </div>
                    <Button size="sm" class="gap-1 text-xs h-7" @click="openAddInstansi">
                        <Plus class="size-3" /> Tambah Instansi
                    </Button>
                </div>
                <div class="overflow-auto max-h-[60svh]">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/20 text-xs text-muted-foreground">
                                <th class="px-3 py-2 text-left font-medium w-10">No</th>
                                <th class="px-3 py-2 text-left font-medium">Nama Instansi</th>
                                <th class="px-3 py-2 text-center font-medium w-20">Unit</th>
                                <th class="px-3 py-2 text-center font-medium w-20">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(ins, idx) in paginatedInstansi"
                                :key="ins.id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <td class="px-3 py-2 text-muted-foreground">{{ (instansiPage - 1) * PAGE_SIZE + idx + 1 }}</td>
                                <td class="px-3 py-2 font-medium">{{ ins.nama_instansi }}</td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        class="text-xs text-primary hover:underline"
                                        @click="filterInstansi = ins.id"
                                    >
                                        {{ ins.unit_kerja?.length ?? 0 }} unit
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-center gap-1">
                                        <button class="rounded p-1 hover:bg-muted" @click="openEditInstansi(ins)">
                                            <Pencil class="size-3.5 text-muted-foreground" />
                                        </button>
                                        <button class="rounded p-1 hover:bg-muted" @click="deleteInstansiId = ins.id">
                                            <Trash2 class="size-3.5 text-red-400" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!paginatedInstansi.length">
                                <td colspan="4" class="px-3 py-6 text-center text-xs text-muted-foreground italic">
                                    Belum ada instansi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <AppPagination v-model:currentPage="instansiPage" :totalPages="totalInstansiPages" />
            </div>

            <!-- ── Unit Kerja Section ───────────────────────────────────────── -->
            <div class="rounded-xl border">
                <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Layers class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold">Unit Kerja</h2>
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                            {{ filteredUnitKerja.length }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Filter by instansi -->
                        <select
                            v-model="filterInstansi"
                            class="h-7 rounded-md border border-input bg-background px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option :value="null">Semua instansi</option>
                            <option v-for="ins in instansi" :key="ins.id" :value="ins.id">
                                {{ ins.nama_instansi }}
                            </option>
                        </select>
                        <Input
                            v-model="search"
                            placeholder="Cari unit kerja..."
                            class="h-7 w-48 text-xs"
                        />
                        <Button size="sm" class="gap-1 text-xs h-7" @click="openAddUK">
                            <Plus class="size-3" /> Tambah
                        </Button>
                    </div>
                </div>

                <div class="overflow-auto max-h-[60svh]">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/20 text-xs text-muted-foreground">
                                <th class="px-3 py-2.5 text-left font-medium w-10">No</th>
                                <th class="px-3 py-2.5 text-left font-medium">Instansi</th>
                                <th class="px-3 py-2.5 text-left font-medium">Kode Unit Kerja</th>
                                <th class="px-3 py-2.5 text-left font-medium">Nama Unit Kerja</th>
                                <th class="px-3 py-2.5 text-center font-medium w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(uk, idx) in paginatedUnitKerja"
                                :key="uk.id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <td class="px-3 py-2.5 text-muted-foreground">{{ (ukPage - 1) * PAGE_SIZE + idx + 1 }}</td>
                                <td class="px-3 py-2.5 text-xs text-muted-foreground">
                                    {{ uk.instansi?.nama_instansi ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5 font-mono text-xs">
                                    {{ uk.kode_unit_kerja ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5">{{ uk.nama_unit_kerja }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center justify-center gap-1">
                                        <button class="rounded p-1 hover:bg-muted" title="Lihat" @click="viewUK = uk">
                                            <Eye class="size-3.5 text-muted-foreground" />
                                        </button>
                                        <button class="rounded p-1 hover:bg-muted" title="Edit" @click="openEditUK(uk)">
                                            <Pencil class="size-3.5 text-muted-foreground" />
                                        </button>
                                        <button class="rounded p-1 hover:bg-muted" title="Hapus" @click="openDeleteUK(uk)">
                                            <Trash2 class="size-3.5 text-red-400" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!paginatedUnitKerja.length">
                                <td colspan="5" class="px-3 py-8 text-center text-xs text-muted-foreground italic">
                                    {{ search || filterInstansi ? 'Tidak ada unit kerja yang cocok.' : 'Belum ada unit kerja.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <AppPagination v-model:currentPage="ukPage" :totalPages="totalUkPages" />
            </div>

        </div>
    </AppLayout>

    <!-- ── Modal Instansi ────────────────────────────────────────────────────── -->
    <Dialog :open="showInstansiModal" @update:open="(v) => { if (!v) showInstansiModal = false; }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ editInstansi ? 'Edit Instansi' : 'Tambah Instansi' }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submitInstansi">
                <div class="flex flex-col gap-1.5">
                    <Label>Nama Instansi <span class="text-red-500">*</span></Label>
                    <Input v-model="instansiForm.nama_instansi" placeholder="Nama instansi..." autofocus />
                    <p v-if="instansiForm.errors.nama_instansi" class="text-xs text-red-500">
                        {{ instansiForm.errors.nama_instansi }}
                    </p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showInstansiModal = false">Batal</Button>
                    <Button type="submit" :disabled="instansiForm.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── Modal Hapus Instansi ──────────────────────────────────────────────── -->
    <Dialog :open="deleteInstansiId !== null" @update:open="(v) => { if (!v) deleteInstansiId = null; }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Hapus Instansi?</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Menghapus instansi juga akan menghapus seluruh unit kerja di dalamnya.
            </p>
            <DialogFooter>
                <Button variant="outline" @click="deleteInstansiId = null">Batal</Button>
                <Button variant="destructive" @click="confirmDeleteInstansi">Hapus</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ── Modal Tambah/Edit Unit Kerja ─────────────────────────────────────── -->
    <Dialog :open="showUKModal" @update:open="(v) => { if (!v) showUKModal = false; }">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editUK ? 'Edit Unit Kerja' : 'Tambah Unit Kerja' }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submitUK">
                <div class="flex flex-col gap-1.5">
                    <Label>Instansi <span class="text-red-500">*</span></Label>
                    <select
                        v-model="ukForm.instansi_id"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option value="">— Pilih Instansi —</option>
                        <option v-for="ins in instansi" :key="ins.id" :value="ins.id">
                            {{ ins.nama_instansi }}
                        </option>
                    </select>
                    <p v-if="ukForm.errors.instansi_id" class="text-xs text-red-500">{{ ukForm.errors.instansi_id }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label>Kode Unit Kerja</Label>
                    <Input v-model="ukForm.kode_unit_kerja" placeholder="Contoh: BDI-001 (opsional)" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label>Nama Unit Kerja <span class="text-red-500">*</span></Label>
                    <Input v-model="ukForm.nama_unit_kerja" placeholder="Nama unit kerja..." />
                    <p v-if="ukForm.errors.nama_unit_kerja" class="text-xs text-red-500">{{ ukForm.errors.nama_unit_kerja }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label>Nama Atasan</Label>
                    <Input v-model="ukForm.nama_atasan" placeholder="Nama atasan (opsional)" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label>NIP</Label>
                    <Input v-model="ukForm.nip" placeholder="NIP atasan (opsional)" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="showUKModal = false">Batal</Button>
                    <Button type="submit" :disabled="ukForm.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── Modal View Unit Kerja ─────────────────────────────────────────────── -->
    <Dialog :open="viewUK !== null" @update:open="(v) => { if (!v) viewUK = null; }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Detail Unit Kerja</DialogTitle>
            </DialogHeader>
            <div v-if="viewUK" class="flex flex-col gap-3 text-sm">
                <div>
                    <p class="text-xs text-muted-foreground">Instansi</p>
                    <p class="font-medium">{{ viewUK.instansi?.nama_instansi ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Kode Unit Kerja</p>
                    <p class="font-mono">{{ viewUK.kode_unit_kerja ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Nama Unit Kerja</p>
                    <p class="font-medium">{{ viewUK.nama_unit_kerja }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Nama Atasan</p>
                    <p class="font-medium">{{ viewUK.nama_atasan ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">NIP</p>
                    <p class="font-mono">{{ viewUK.nip ?? '—' }}</p>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="viewUK = null">Tutup</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ── Modal Hapus Unit Kerja ────────────────────────────────────────────── -->
    <Dialog :open="deleteUKId !== null" @update:open="(v) => { if (!v) deleteUKId = null; }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Hapus Unit Kerja?</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Unit kerja "<strong>{{ deleteUKLabel }}</strong>" akan dihapus.
            </p>
            <DialogFooter>
                <Button variant="outline" @click="deleteUKId = null">Batal</Button>
                <Button variant="destructive" @click="confirmDeleteUK">Hapus</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
