<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { PaginationLink } from '@/types';

const props = defineProps<{
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}>();

function isPrevious(index: number): boolean {
    return index === 0;
}

function isNext(index: number): boolean {
    return index === props.links.length - 1;
}

function isEdge(index: number): boolean {
    return isPrevious(index) || isNext(index);
}
</script>

<template>
    <div
        v-if="total > 0"
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-sm text-muted-foreground">
            Showing {{ from }} to {{ to }} of {{ total }} results
        </p>

        <nav
            v-if="links.length > 3"
            class="flex flex-wrap items-center gap-1"
            aria-label="Pagination"
        >
            <template v-for="(link, index) in links" :key="index">
                <template v-if="isEdge(index)">
                    <Button
                        v-if="link.url"
                        variant="outline"
                        size="icon-sm"
                        as-child
                    >
                        <Link :href="link.url" preserve-scroll>
                            <component
                                :is="
                                    isPrevious(index)
                                        ? ChevronLeft
                                        : ChevronRight
                                "
                                class="size-4"
                            />
                        </Link>
                    </Button>
                    <Button v-else variant="outline" size="icon-sm" disabled>
                        <component
                            :is="isPrevious(index) ? ChevronLeft : ChevronRight"
                            class="size-4"
                        />
                    </Button>
                </template>

                <Button
                    v-else-if="link.url"
                    :variant="link.active ? 'default' : 'outline'"
                    size="icon-sm"
                    as-child
                >
                    <Link :href="link.url" preserve-scroll>
                        {{ link.label }}
                    </Link>
                </Button>

                <span v-else class="px-2 text-sm text-muted-foreground">
                    {{ link.label }}
                </span>
            </template>
        </nav>
    </div>
</template>
