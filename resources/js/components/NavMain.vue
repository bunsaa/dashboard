<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { ref, watchEffect } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

// Reactive open state per collapsible group title
const openStates = ref<Record<string, boolean>>({});

// Auto-open any group whose sub-item matches the current URL (reactive across SPA navigations)
watchEffect(() => {
    for (const item of props.items) {
        if (!item.items?.length) continue;
        if (item.items.some((sub) => !!sub.href && isCurrentUrl(sub.href))) {
            openStates.value[item.title] = true;
        }
    }
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <!-- Collapsible item with sub-menu -->
                <Collapsible
                    v-if="item.items && item.items.length"
                    :open="openStates[item.title] ?? false"
                    class="group/collapsible"
                    @update:open="(val: boolean) => (openStates[item.title] = val)"
                >
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton :tooltip="item.title">
                            <component :is="item.icon" v-if="item.icon" />
                            <span>{{ item.title }}</span>
                            <ChevronDown
                                class="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-180"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem v-for="sub in item.items" :key="sub.title">
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="!!sub.href && isCurrentUrl(sub.href)"
                                >
                                    <Link :href="sub.href ?? '/'" prefetch>
                                        <component :is="sub.icon" v-if="sub.icon" class="size-3.5" />
                                        <span>{{ sub.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </Collapsible>

                <!-- Regular nav item -->
                <SidebarMenuButton
                    v-else
                    as-child
                    :is-active="!!item.href && isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href ?? '/'" prefetch>
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
