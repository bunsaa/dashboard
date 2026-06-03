<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps<{
    currentPage: number;
    totalPages: number;
}>();

const emit = defineEmits<{
    'update:currentPage': [value: number];
}>();

const pages = computed(() => {
    const total = props.totalPages;
    const current = props.currentPage;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    const result: (number | '...')[] = [1];
    if (current > 3) result.push('...');
    for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
        result.push(i);
    }
    if (current < total - 2) result.push('...');
    result.push(total);
    return result;
});

function goTo(page: number) {
    if (page >= 1 && page <= props.totalPages) {
        emit('update:currentPage', page);
    }
}
</script>

<template>
    <div v-if="totalPages > 1" class="flex items-center justify-center gap-1 border-t px-4 py-2">
        <Button
            variant="ghost"
            size="icon"
            class="size-7"
            :disabled="currentPage <= 1"
            @click="goTo(currentPage - 1)"
        >
            <ChevronLeft class="size-3.5" />
        </Button>

        <template v-for="p in pages" :key="typeof p === 'number' ? p : `ellipsis-${p}`">
            <span v-if="p === '...'" class="px-1 text-xs text-muted-foreground">…</span>
            <Button
                v-else
                :variant="p === currentPage ? 'default' : 'ghost'"
                size="icon"
                class="size-7 text-xs"
                @click="goTo(p)"
            >
                {{ p }}
            </Button>
        </template>

        <Button
            variant="ghost"
            size="icon"
            class="size-7"
            :disabled="currentPage >= totalPages"
            @click="goTo(currentPage + 1)"
        >
            <ChevronRight class="size-3.5" />
        </Button>
    </div>
</template>
