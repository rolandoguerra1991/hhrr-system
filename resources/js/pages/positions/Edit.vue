<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import PositionController from '@/actions/App/Http/Controllers/PositionController';
import PositionForm from '@/components/positions/PositionForm.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { DepartmentOption, PositionDetails } from '@/types';

const props = defineProps<{
    position: PositionDetails;
    departmentOptions: DepartmentOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Positions',
                href: PositionController.index.url(),
            },
            {
                title: 'Edit position',
                href: PositionController.index.url(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${position.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link :href="PositionController.index.url()">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <Heading
                :title="`Edit ${position.name}`"
                description="Rename the position or move it to another department."
            />
        </div>

        <PositionForm
            :position="props.position"
            :department-options="props.departmentOptions"
        />
    </div>
</template>
