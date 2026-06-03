<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { AlertCircle, Download } from 'lucide-vue-next';
import { computed } from 'vue';
import { downloadReport } from '@/routes';
import downloadReportRoutes from '@/routes/download-report';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Download Report',
                href: props.currentTeam ? downloadReport(props.currentTeam.slug).url : '/',
            },
        ],
    }),
});

const props = defineProps<{
    rekap: Array<{
        No: number;
        NamaPoli: string;
        NamaDokter: string;
        JmlPasienTahunLalu: number;
        JmlPasienTahunBerjalan: number;
    }>;
    tahun: number;
    error: string | null;
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);

const excelUrl = computed(() =>
    currentTeam.value ? downloadReportRoutes.rawatJalan.excel(currentTeam.value.slug).url : '#',
);
</script>

<template>
    <Head title="Download Report" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 xl:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Laporan Kunjungan Pasien</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Data kunjungan rawat jalan RSUD Tarakan tahun {{ props.tahun - 1 }}–{{ props.tahun }}
                </p>
            </div>
            <a
                :href="excelUrl"
                :class="props.error ? 'pointer-events-none opacity-40' : 'hover:bg-emerald-700'"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
            >
                <Download :size="16" />
                Download Excel
            </a>
        </div>

        <!-- Error alert -->
        <div
            v-if="props.error"
            class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            <AlertCircle :size="18" class="mt-0.5 shrink-0" />
            <p>{{ props.error }}</p>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">No</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Poli</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Dokter</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">
                                Jml Pasien {{ props.tahun - 1 }}
                            </th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">
                                Jml Pasien {{ props.tahun }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="!props.rekap.length">
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                {{
                                    props.error
                                        ? 'Tidak ada data — koneksi ke database TARAKAN gagal.'
                                        : 'Tidak ada data.'
                                }}
                            </td>
                        </tr>
                        <tr
                            v-for="row in props.rekap"
                            :key="row.No"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30"
                        >
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ row.No }}</td>
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ row.NamaPoli }}</td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ row.NamaDokter }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{ row.JmlPasienTahunLalu.toLocaleString('id-ID') }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{ row.JmlPasienTahunBerjalan.toLocaleString('id-ID') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</template>
