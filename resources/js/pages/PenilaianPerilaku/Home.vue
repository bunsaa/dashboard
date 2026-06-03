<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ChevronRightIcon, ClipboardListIcon, SettingsIcon, StarIcon, UserCheckIcon, UsersIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import penilaianPerilaku from '@/routes/penilaian-perilaku';
import manajemenPegawai from '@/routes/manajemen-pegawai';
import type { Team } from '@/types';

const props = defineProps<{
    user: {
        name: string;
        role: string;
        penilaian_aktif: boolean;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Dashboard', href: '/' },
            { title: 'Penilaian Perilaku', href: '#' },
        ],
    }),
});

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team);

const isAdmin = computed(() => props.user.role === 'admin_mutu');
const isKepalaUnit = computed(() => props.user.role === 'kepala_unit');
const isStaf = computed(() => props.user.role === 'staf');

const roleLabel = computed(() => {
    if (isAdmin.value) return 'Admin Mutu';
    if (isKepalaUnit.value) return 'Kepala Unit';
    if (isStaf.value) return 'Staf';
    return props.user.role;
});

const roleColor = computed(() => {
    if (isAdmin.value) return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
    if (isKepalaUnit.value) return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300';
    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 11) return 'Selamat Pagi';
    if (hour < 15) return 'Selamat Siang';
    if (hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});
</script>

<template>
    <Head title="Penilaian Perilaku" />
    <div class="p-4 space-y-4 xl:p-6 xl:space-y-6">

        <!-- Welcome Banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-500 to-purple-600 p-6 text-white shadow-lg shadow-indigo-500/20">
            <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-10 -left-6 h-36 w-36 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute right-24 bottom-2 h-20 w-20 rounded-full bg-white/5"></div>

            <div class="relative z-10 flex items-start gap-4">
                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm text-2xl font-bold shadow-inner">
                    {{ user.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                    <p class="text-sm font-medium text-indigo-200">{{ greeting }},</p>
                    <h1 class="mt-0.5 text-2xl font-bold leading-tight">{{ user.name }}</h1>
                    <span
                        class="mt-2 inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-0.5 text-xs font-semibold backdrop-blur-sm"
                    >
                        <StarIcon :size="11" class="fill-current" />
                        {{ roleLabel }}
                    </span>
                </div>
            </div>

            <p class="relative z-10 mt-5 text-sm text-indigo-100 leading-relaxed max-w-md">
                Selamat datang di modul <strong>Penilaian Perilaku</strong>.
                Gunakan menu di bawah ini untuk mengelola penilaian perilaku pegawai.
            </p>
        </div>

        <!-- Quick Action Cards -->
        <div>
            <h2 class="mb-3 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Menu Cepat</h2>

            <div class="grid gap-3 sm:grid-cols-2">

                <!-- Penilaian Perilaku Pegawai (kepala_unit + admin) -->
                <a
                    v-if="isKepalaUnit || isAdmin"
                    :href="penilaianPerilaku.pegawai(currentTeam.slug).url"
                    class="group flex items-center gap-4 rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:border-indigo-400 hover:shadow-md hover:shadow-indigo-500/10"
                >
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-colors dark:bg-indigo-900/30 dark:text-indigo-400">
                        <ClipboardListIcon :size="22" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-foreground">Penilaian Perilaku Pegawai</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Kelola penilaian perilaku staf di unit Anda</p>
                    </div>
                    <ChevronRightIcon :size="16" class="flex-shrink-0 text-muted-foreground group-hover:text-indigo-500 transition-colors" />
                </a>

                <!-- Penilaian Perilaku Saya (staf) -->
                <a
                    v-if="isStaf"
                    :href="penilaianPerilaku.saya(currentTeam.slug).url"
                    class="group flex items-center gap-4 rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:border-green-400 hover:shadow-md hover:shadow-green-500/10"
                >
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-600 group-hover:bg-green-600 group-hover:text-white transition-colors dark:bg-green-900/30 dark:text-green-400">
                        <UserCheckIcon :size="22" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-foreground">Penilaian Perilaku Saya</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Lihat hasil penilaian perilaku Anda</p>
                    </div>
                    <ChevronRightIcon :size="16" class="flex-shrink-0 text-muted-foreground group-hover:text-green-500 transition-colors" />
                </a>

                <!-- Manajemen Pegawai (admin only) -->
                <a
                    v-if="isAdmin"
                    :href="manajemenPegawai.index(currentTeam.slug).url"
                    class="group flex items-center gap-4 rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:border-blue-400 hover:shadow-md hover:shadow-blue-500/10"
                >
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors dark:bg-blue-900/30 dark:text-blue-400">
                        <UsersIcon :size="22" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-foreground">Manajemen Pegawai</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Kelola data pegawai dan unit kerja</p>
                    </div>
                    <ChevronRightIcon :size="16" class="flex-shrink-0 text-muted-foreground group-hover:text-blue-500 transition-colors" />
                </a>

                <!-- Pengaturan Penilaian (admin only) -->
                <a
                    v-if="isAdmin"
                    :href="penilaianPerilaku.pengaturan(currentTeam.slug).url"
                    class="group flex items-center gap-4 rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:border-purple-400 hover:shadow-md hover:shadow-purple-500/10"
                >
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition-colors dark:bg-purple-900/30 dark:text-purple-400">
                        <SettingsIcon :size="22" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-foreground">Pengaturan Penilaian</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Konfigurasi periode dan status penilaian</p>
                    </div>
                    <ChevronRightIcon :size="16" class="flex-shrink-0 text-muted-foreground group-hover:text-purple-500 transition-colors" />
                </a>

            </div>
        </div>

    </div>
</template>
