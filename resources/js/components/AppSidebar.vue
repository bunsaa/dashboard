<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ActivitySquare, ClipboardList, ClipboardPen, Download, LayoutGrid, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavMonev from '@/components/NavMonev.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import downloadReport from '@/routes/download-report';
import monitoring from '@/routes/monitoring';
import { bed, dashboard } from '@/routes';
import manajemenPegawai from '@/routes/manajemen-pegawai';
import penilaianPerilaku from '@/routes/penilaian-perilaku';
import renkin from '@/routes/renkin';
import type { NavItem, Team } from '@/types';

const page = usePage();

const currentTeam = computed(() => page.props.currentTeam as Team | null);

const dashboardUrl = computed(() =>
    currentTeam.value ? dashboard(currentTeam.value.slug).url : '/',
);

const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'staf');
const userKodeUnit = computed(() => (page.props.auth as any)?.user?.kode_unit ?? '');
const userStatusPegawai = computed(() => (page.props.auth as any)?.user?.status_pegawai ?? '');
const isAsn = computed(() => ['PNS', 'CPNS', 'PPPK'].includes(userStatusPegawai.value));
const hidePenilaian = computed(() => isAsn.value && userRole.value === 'staf');
const isAdminMutu = computed(() => userRole.value === 'admin_mutu');
const isDatin = computed(() => userKodeUnit.value === 'Datin');

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl.value,
            icon: LayoutGrid,
        },
    ];

    if (!hidePenilaian.value) {
        items.push({
            title: 'Penilaian Perilaku',
            href: currentTeam.value ? penilaianPerilaku.home(currentTeam.value.slug).url : '/',
            icon: ClipboardPen,
        });
    }

    if (isDatin.value || isAdminMutu.value) {
        items.push({
            title: 'e-Komplain IT',
            href: currentTeam.value ? renkin.googleReviews(currentTeam.value.slug).url : '/',
            icon: ClipboardList,
        });
    }

    items.push({
        title: 'Download Report',
        icon: Download,
        items: [
            {
                title: 'Rawat Jalan',
                href: currentTeam.value ? downloadReport.rawatJalan(currentTeam.value.slug).url : '/',
            },
            {
                title: 'Rawat Inap',
                href: currentTeam.value ? downloadReport.rawatInap(currentTeam.value.slug).url : '/',
            },
            {
                title: 'Billing NonBPJS',
                href: currentTeam.value ? downloadReport.billingNonBpjs(currentTeam.value.slug).url : '/',
            },
            {
                title: 'Data Kunjungan Dokter',
                href: currentTeam.value ? downloadReport.kunjunganDokter(currentTeam.value.slug).url : '/',
            },
            {
                title: 'Kunjungan Pasien',
                href: currentTeam.value ? downloadReport.kunjunganPasien(currentTeam.value.slug).url : '/',
            },
        ],
    });

    items.push({
        title: 'Monitoring',
        icon: ActivitySquare,
        items: [
            {
                title: 'BED RSUD Tarakan',
                href: currentTeam.value ? bed(currentTeam.value.slug).url : '/',
            },
            {
                title: 'Beda Kelas Peserta',
                href: currentTeam.value ? monitoring.bedaKelasPeserta(currentTeam.value.slug).url : '/',
            },
            {
                title: 'Klaim BPJS',
                href: currentTeam.value ? monitoring.klaimBpjs(currentTeam.value.slug).url : '/',
            },
        ],
    });

    return items;
});

const manajemenPegawaiUrl = computed(() =>
    currentTeam.value ? manajemenPegawai.index(currentTeam.value.slug).url : '/',
);

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMonev />

            <!-- Admin: Manajemen -->
            <SidebarGroup v-if="isAdminMutu" class="px-2 py-0">
                <SidebarGroupLabel>Manajemen</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child tooltip="Manajemen Pegawai">
                            <Link :href="manajemenPegawaiUrl">
                                <Users class="size-4" />
                                <span>Manajemen Pegawai</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
