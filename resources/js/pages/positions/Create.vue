<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import PositionController from '@/actions/App/Http/Controllers/PositionController';
import PositionForm from '@/components/positions/PositionForm.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { DepartmentOption } from '@/types';

const props = defineProps<{
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
                title: 'New position',
                href: PositionController.create.url(),
            },
        ],
    },
});
</script>

<template>
    <Head title="New position" />

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
                title="New position"
                description="Add a job title to the catalogue and scope it to a department."
            />
        </div>

        <PositionForm
            :department-options="props.departmentOptions"
            sticky-actions
        />
    </div>
</template>
