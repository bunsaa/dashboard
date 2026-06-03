<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BarChart2, ClipboardList, ClipboardPen, Download, LayoutGrid, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { dashboard, downloadReport } from '@/routes';
import manajemenPegawai from '@/routes/manajemen-pegawai';
import monev from '@/routes/monev';
import penilaianPerilaku from '@/routes/penilaian-perilaku';
import renkin from '@/routes/renkin';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const userRole = computed(() => user.value?.role ?? 'staf');
const userKodeUnit = computed(() => user.value?.kode_unit ?? '');
const isDatin = computed(() => userKodeUnit.value === 'Datin');
const isAdminMutu = computed(() => userRole.value === 'admin_mutu');
const isAsn = computed(() => ['PNS', 'CPNS', 'PPPK'].includes(user.value?.status_pegawai ?? ''));
const hidePenilaian = computed(() => isAsn.value && userRole.value === 'staf');

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Selamat Pagi';
    if (hour < 15) return 'Selamat Siang';
    if (hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});

const menuItems = computed(() => {
    if (!currentTeam.value) return [];

    const items = [];

    if (!hidePenilaian.value) {
        items.push({
            title: 'Penilaian Perilaku',
            description: 'Kelola dan pantau penilaian perilaku pegawai per periode',
            href: penilaianPerilaku.home(currentTeam.value.slug).url,
            icon: ClipboardPen,
            bg: 'bg-indigo-50 dark:bg-indigo-900/20',
            iconColor: 'text-indigo-600 dark:text-indigo-400',
            border: 'hover:border-indigo-300 dark:hover:border-indigo-700',
        });
    }

    if (isDatin.value || isAdminMutu.value) {
        items.push({
            title: 'e-Komplain IT',
            description: 'Monitoring dan analisis ulasan Google untuk layanan IT',
            href: renkin.googleReviews(currentTeam.value.slug).url,
            icon: ClipboardList,
            bg: 'bg-emerald-50 dark:bg-emerald-900/20',
            iconColor: 'text-emerald-600 dark:text-emerald-400',
            border: 'hover:border-emerald-300 dark:hover:border-emerald-700',
        });
    }

    items.push({
        title: 'Monev',
        description: 'Monitoring dan evaluasi aktivitas, kontrak, dan progress pekerjaan',
        href: monev.dashboard(currentTeam.value.slug).url,
        icon: BarChart2,
        bg: 'bg-amber-50 dark:bg-amber-900/20',
        iconColor: 'text-amber-600 dark:text-amber-400',
        border: 'hover:border-amber-300 dark:hover:border-amber-700',
    });

    items.push({
        title: 'Download Report',
        description: 'Unduh laporan kunjungan pasien rawat jalan RSUD Tarakan',
        href: downloadReport(currentTeam.value.slug).url,
        icon: Download,
        bg: 'bg-teal-50 dark:bg-teal-900/20',
        iconColor: 'text-teal-600 dark:text-teal-400',
        border: 'hover:border-teal-300 dark:hover:border-teal-700',
    });

    if (userRole.value === 'admin_mutu') {
        items.push({
            title: 'Manajemen Pegawai',
            description: 'Kelola data pegawai, unit kerja, dan akses pengguna',
            href: manajemenPegawai.index(currentTeam.value.slug).url,
            icon: Users,
            bg: 'bg-violet-50 dark:bg-violet-900/20',
            iconColor: 'text-violet-600 dark:text-violet-400',
            border: 'hover:border-violet-300 dark:hover:border-violet-700',
        });
    }

    return items;
});

const today = computed(() =>
    new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 xl:p-6">

        <!-- Welcome Banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-blue-800 p-6 text-white shadow-lg">
            <div class="relative z-10">
                <p class="text-xs font-medium text-indigo-200">{{ today }}</p>
                <h1 class="mt-1 text-xl font-bold sm:text-2xl">{{ greeting }}, {{ user?.name?.split(' ')[0] ?? 'Pengguna' }}</h1>
                <p class="mt-1 text-sm text-indigo-200">
                    Selamat datang di Dashboard RSUD Tarakan.
                    <span v-if="currentTeam">Tim aktif: <strong class="text-white">{{ currentTeam.name }}</strong></span>
                </p>
            </div>
            <div class="absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-8 right-16 h-28 w-28 rounded-full bg-white/5"></div>
            <div class="absolute bottom-3 right-5 h-14 w-14 rounded-full bg-white/10"></div>
        </div>

        <!-- Quick Access Menu -->
        <div>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Menu Utama</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="item in menuItems"
                    :key="item.title"
                    :href="item.href"
                    class="group flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                    :class="item.border"
                >
                    <div class="flex items-start justify-between">
                        <div class="rounded-xl p-2.5" :class="item.bg">
                            <component :is="item.icon" :size="20" :class="item.iconColor" />
                        </div>
                        <svg class="h-4 w-4 translate-x-0 text-gray-300 transition-transform group-hover:translate-x-1 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ item.title }}</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Info Row -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="rounded-lg bg-blue-50 p-2 dark:bg-blue-900/20">
                    <LayoutGrid :size="16" class="text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tim Aktif</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ currentTeam?.name ?? '-' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="rounded-lg bg-purple-50 p-2 dark:bg-purple-900/20">
                    <Users :size="16" class="text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Peran Anda</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ userRole === 'admin_mutu' ? 'Admin Mutu' : userRole === 'kepala_unit' ? 'Kepala Unit' : 'Staf' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="rounded-lg bg-green-50 p-2 dark:bg-green-900/20">
                    <ClipboardPen :size="16" class="text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Periode Penilaian</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }) }}
                    </p>
                </div>
            </div>
        </div>

    </div>
</template>
