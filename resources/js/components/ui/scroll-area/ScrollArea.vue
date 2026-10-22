<script setup lang="ts">
import type { ScrollAreaRootProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { ScrollAreaRoot, ScrollAreaViewport } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<
    ScrollAreaRootProps & { class?: HTMLAttributes['class'] }
>();
</script>

<template>
    <ScrollAreaRoot
        data-slot="scroll-area"
        v-bind="props"
        :class="cn('relative overflow-hidden', props.class)"
    >
        <ScrollAreaViewport
            data-slot="scroll-area-viewport"
            class="size-full rounded-[inherit]"
        >
            <slot />
        </ScrollAreaViewport>
        <slot name="scrollbar" />
    </ScrollAreaRoot>
</template>
