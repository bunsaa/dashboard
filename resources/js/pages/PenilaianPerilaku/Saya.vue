<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import penilaianPerilaku from '@/routes/penilaian-perilaku';
import type { Team } from '@/types';

interface PenilaianRow {
    id: number;
    bulan: string;
    periode: string;
    berorientasi_pelayanan: number;
    akuntabel: number;
    kompeten: number;
    harmonis: number;
    loyal: number;
    adaptif: number;
    kolaboratif: number;
    rata_rata: number;
    keterangan: string;
}

const props = defineProps<{
    penilaianList: PenilaianRow[];
    tahun: number;
    userName: string;
}>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: '/' },
            { title: 'Penilaian Perilaku', href: layoutProps.currentTeam ? penilaianPerilaku.home(layoutProps.currentTeam.slug).url : '/' },
            { title: 'Penilaian Perilaku Saya', href: '#' },
        ],
    }),
});

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team);

const filterTahun = ref(props.tahun);

const tahunOptions = computed(() => {
    const now = new Date().getFullYear();
    const options: number[] = [];
    for (let y = now; y >= now - 4; y--) {
        options.push(y);
    }
    return options;
});

function tampilkan() {
    router.get(
        penilaianPerilaku.saya(currentTeam.value.slug).url,
        { tahun: filterTahun.value },
        { preserveState: true, preserveScroll: true },
    );
}

const keteranganColor = (keterangan: string) => {
    if (keterangan.includes('Atas')) return 'text-green-600 dark:text-green-400 font-semibold';
    if (keterangan.includes('Sesuai')) return 'text-blue-600 dark:text-blue-400 font-semibold';
    return 'text-red-600 dark:text-red-400 font-semibold';
};
</script>

<template>
    <Head title="Penilaian Perilaku Saya" />
    <div class="flex h-full flex-1 flex-col gap-4 p-4 xl:p-6">

        <h2 class="text-xl font-bold dark:text-gray-100">Lihat Hasil Penilaian Perilaku Pegawai</h2>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm font-medium dark:text-gray-200">Daftar Nilai Pegawai <strong>{{ userName }}</strong></p>
        </div>

        <!-- Filter Tahun -->
        <div class="flex items-end gap-3">
            <label class="text-sm">
                <span class="mb-1 block font-semibold dark:text-gray-200">Pilih Tahun</span>
                <select
                    v-model="filterTahun"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                    <option v-for="y in tahunOptions" :key="y" :value="y">{{ y }}</option>
                </select>
            </label>
            <button @click="tampilkan" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 shadow-md transition-colors">
                Tampilkan
            </button>
        </div>

        <!-- Table -->
        <div class="overflow-auto max-h-[60svh] border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-10">#</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Bulan</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Berorientasi Pelayanan</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Akuntabel</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Kompeten</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Harmonis</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Loyal</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Adaptif</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Kolaboratif</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Nilai Rata Rata</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="(row, idx) in penilaianList" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-3 py-3 text-gray-900 dark:text-gray-100">{{ idx + 1 }}</td>
                        <td class="px-3 py-3 text-gray-900 dark:text-gray-100 font-medium uppercase">{{ row.bulan }}</td>
                        <td class="px-3 py-3 text-center text-gray-900 dark:text-gray-100">{{ row.berorientasi_pelayanan }}</td>
                        <td class="px-3 py-3 text-center text-gray-900 dark:text-gray-100">{{ row.akuntabel }}</td>
                        <td class="px-3 py-3 text-center text-gray-900 dark:text-gray-100">{{ row.kompeten }}</td>
                        <td class="px-3 py-3 text-center text-gray-900 dark:text-gray-100">{{ row.harmonis }}</td>
                        <td class="px-3 py-3 text-center text-gray-900 dark:text-gray-100">{{ row.loyal }}</td>
                        <td class="px-3 py-3 text-center text-gray-900 dark:text-gray-100">{{ row.adaptif }}</td>
                        <td class="px-3 py-3 text-center text-gray-900 dark:text-gray-100">{{ row.kolaboratif }}</td>
                        <td class="px-3 py-3 text-center font-semibold text-gray-900 dark:text-gray-100">{{ row.rata_rata }}</td>
                        <td class="px-3 py-3" :class="keteranganColor(row.keterangan)">{{ row.keterangan }}</td>
                    </tr>
                    <tr v-if="penilaianList.length === 0">
                        <td colspan="11" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                            Belum ada data penilaian untuk tahun {{ tahun }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Menampilkan baris 1 - {{ penilaianList.length }} dari {{ penilaianList.length }} baris
        </p>
    </div>
</template>
