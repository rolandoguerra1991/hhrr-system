<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import DepartmentController from '@/actions/App/Http/Controllers/DepartmentController';
import DepartmentForm from '@/components/departments/DepartmentForm.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { DepartmentManagerOption, DepartmentOption } from '@/types';

const props = defineProps<{
    parentOptions: DepartmentOption[];
    managers: DepartmentManagerOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Departments',
                href: DepartmentController.index.url(),
            },
            {
                title: 'New department',
                href: DepartmentController.create.url(),
            },
        ],
    },
});
</script>

<template>
    <Head title="New department" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link :href="DepartmentController.index.url()">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <Heading
                title="New department"
                description="Add a department and place it in the company structure."
            />
        </div>

        <DepartmentForm
            :parent-options="props.parentOptions"
            :managers="props.managers"
            sticky-actions
        />
    </div>
</template>
