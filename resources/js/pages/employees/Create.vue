<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeForm from '@/components/employees/EmployeeForm.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { EmployeeUserOption } from '@/types';

const props = defineProps<{
    users: EmployeeUserOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Employees',
                href: EmployeeController.index.url(),
            },
            {
                title: 'New employee',
                href: EmployeeController.create.url(),
            },
        ],
    },
});
</script>

<template>
    <Head title="New employee" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link :href="EmployeeController.index.url()">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <Heading
                title="New employee"
                description="Create an employee record and begin the first employment period."
            />
        </div>

        <EmployeeForm :users="props.users" sticky-actions />
    </div>
</template>
