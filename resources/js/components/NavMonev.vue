<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, ClipboardList } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { Team } from '@/types';

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'staf');
const isMonevAdmin = computed(() => ['admin_mutu', 'kepala_unit'].includes(userRole.value));

const monevBaseUrl = computed(() =>
    currentTeam.value ? `/${currentTeam.value.slug}/monev` : '/monev',
);

const subItems = computed(() => [
    { title: 'Dashboard', href: `${monevBaseUrl.value}/dashboard` },
    ...(isMonevAdmin.value ? [{ title: 'Unit Kerja', href: `${monevBaseUrl.value}/unit-kerja` }] : []),
    { title: 'Aktivitas', href: `${monevBaseUrl.value}/aktivitas` },
    { title: 'Kontrak', href: `${monevBaseUrl.value}/kontrak` },
    { title: 'Progress', href: `${monevBaseUrl.value}/progress` },
    ...(isMonevAdmin.value ? [{ title: 'Vendor', href: `${monevBaseUrl.value}/vendor` }] : []),
]);

const isOpen = ref(isCurrentOrParentUrl(monevBaseUrl.value));

watchEffect(() => {
    if (isCurrentOrParentUrl(monevBaseUrl.value)) {
        isOpen.value = true;
    }
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <Collapsible v-model:open="isOpen" class="group/collapsible">
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton tooltip="Monev">
                            <ClipboardList class="size-4" />
                            <span>Monev</span>
                            <ChevronDown
                                class="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-180"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem v-for="item in subItems" :key="item.title">
                                <SidebarMenuSubButton as-child :is-active="isCurrentUrl(item.href)">
                                    <Link :href="item.href" prefetch>
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
