<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import penilaianPerilaku from '@/routes/penilaian-perilaku';
import type { Team } from '@/types';

interface KepalaItem {
    id: number;
    name: string;
    unit_nama: string;
    penilaian_aktif: boolean;
}

const props = defineProps<{
    kepalaList: KepalaItem[];
    isPeriod: boolean;
}>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: '/' },
            { title: 'Penilaian Perilaku', href: layoutProps.currentTeam ? penilaianPerilaku.home(layoutProps.currentTeam.slug).url : '/' },
            { title: 'Pengaturan Penilaian', href: '#' },
        ],
    }),
});

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team);

const list = ref<KepalaItem[]>(props.kepalaList.map((k) => ({ ...k })));
const loading = ref<Record<number, boolean>>({});
const loadingAll = ref(false);

const flash = ref<{ type: string; message: string } | null>(null);
function showFlash(type: string, message: string) {
    flash.value = { type, message };
    setTimeout(() => {
        flash.value = null;
    }, 3000);
}

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

async function togglePenilaian(kepala: KepalaItem) {
    loading.value[kepala.id] = true;
    try {
        const res = await fetch(penilaianPerilaku.pengaturan.toggle(currentTeam.value.slug, kepala.id).url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        const data = await res.json();
        kepala.penilaian_aktif = data.penilaian_aktif;
        showFlash('success', `Penilaian ${kepala.name} ${kepala.penilaian_aktif ? 'diaktifkan' : 'dinonaktifkan'}`);
    } catch {
        showFlash('error', 'Gagal mengubah status');
    } finally {
        loading.value[kepala.id] = false;
    }
}

async function toggleAll(aktif: boolean) {
    loadingAll.value = true;
    try {
        await fetch(penilaianPerilaku.pengaturan.toggleAll(currentTeam.value.slug).url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ aktif }),
        });
        list.value.forEach((k) => (k.penilaian_aktif = aktif));
        showFlash('success', aktif ? 'Semua penilaian diaktifkan' : 'Semua penilaian dinonaktifkan');
    } catch {
        showFlash('error', 'Gagal mengubah status');
    } finally {
        loadingAll.value = false;
    }
}
</script>

<template>
    <Head title="Pengaturan Penilaian" />
    <div class="flex h-full flex-1 flex-col gap-4 p-4 xl:p-6">

        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="text-xl font-bold dark:text-gray-100">Pengaturan Penilaian Perilaku</h2>
            <div class="flex gap-2">
                <button
                    @click="toggleAll(true)"
                    :disabled="loadingAll"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 shadow-md transition-colors disabled:opacity-50"
                >
                    Aktifkan Semua
                </button>
                <button
                    @click="toggleAll(false)"
                    :disabled="loadingAll"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 shadow-md transition-colors disabled:opacity-50"
                >
                    Nonaktifkan Semua
                </button>
            </div>
        </div>

        <!-- Auto-period info -->
        <div
            class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm border"
            :class="isPeriod
                ? 'bg-green-50 border-green-300 text-green-800 dark:bg-green-900/20 dark:border-green-700 dark:text-green-300'
                : 'bg-amber-50 border-amber-300 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300'"
        >
            <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
            </svg>
            <span v-if="isPeriod">
                <strong>Periode penilaian aktif</strong> — Semua kepala unit otomatis diaktifkan (tgl 15 s/d 5 bulan berikutnya).
            </span>
            <span v-else>
                <strong>Di luar periode penilaian</strong> — Semua kepala unit otomatis dinonaktifkan (tgl 6–14). Aktif kembali otomatis pada tanggal 15.
            </span>
        </div>

        <!-- Flash Message -->
        <transition name="fade" mode="out-in">
            <div
                v-if="flash"
                :class="flash.type === 'success'
                    ? 'bg-green-50 border-green-400 text-green-700 dark:bg-green-900/30 dark:border-green-600 dark:text-green-300'
                    : 'bg-red-50 border-red-400 text-red-700 dark:bg-red-900/30 dark:border-red-600 dark:text-red-300'"
                class="rounded-lg border px-4 py-3 text-sm"
            >
                {{ flash.message }}
            </div>
        </transition>

        <!-- Table -->
        <div class="overflow-auto max-h-[60svh] border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-10">No</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Nama</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Unit</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="(kepala, idx) in list" :key="kepala.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-3 py-3 text-gray-900 dark:text-gray-100">{{ idx + 1 }}</td>
                        <td class="px-3 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ kepala.name }}</td>
                        <td class="px-3 py-3 text-gray-900 dark:text-gray-100">{{ kepala.unit_nama }}</td>
                        <td class="px-3 py-3 text-center">
                            <button
                                @click="togglePenilaian(kepala)"
                                :disabled="loading[kepala.id]"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                :class="kepala.penilaian_aktif ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"
                                    :class="kepala.penilaian_aktif ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </button>
                            <span class="ml-2 text-xs" :class="kepala.penilaian_aktif ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                                {{ kepala.penilaian_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="list.length === 0">
                        <td colspan="4" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                            Tidak ada data kepala unit
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
