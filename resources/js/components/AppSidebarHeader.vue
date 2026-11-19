<script setup lang="ts">
import { PanelLeftClose, PanelLeftOpen } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { isMobile, openMobile, state, toggleSidebar } = useSidebar();

const menuOpen = computed(() => (isMobile.value ? openMobile.value : state.value === 'expanded'));
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <Button type="button" variant="outline" size="sm" class="-ml-1" @click="toggleSidebar">
                <PanelLeftClose v-if="menuOpen" class="size-4" />
                <PanelLeftOpen v-else class="size-4" />
                {{ menuOpen ? 'Ocultar menú' : 'Mostrar menú' }}
            </Button>
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
