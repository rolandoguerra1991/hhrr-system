<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeForm from '@/components/employees/EmployeeForm.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { EmployeeFormDetails, EmployeeUserOption } from '@/types';

const props = defineProps<{
    employee: EmployeeFormDetails;
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
                title: 'Edit employee',
                href: EmployeeController.index.url(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${employee.first_name} ${employee.last_name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link :href="EmployeeController.show.url(employee.id)">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <Heading
                :title="`Edit ${employee.first_name} ${employee.last_name}`"
                description="Changes to employee details and account links require a recorded reason."
            />
        </div>

        <EmployeeForm
            :employee="props.employee"
            :users="props.users"
            sticky-actions
        />
    </div>
</template>
