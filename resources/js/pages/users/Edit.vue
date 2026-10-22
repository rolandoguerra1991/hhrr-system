<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import UserForm from '@/components/users/UserForm.vue';
import { Button } from '@/components/ui/button';
import type { DepartmentOption, UserAbility, UserRoleOption } from '@/types';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        department_id: number | null;
    };
    roleNames: string[];
    roles: UserRoleOption[];
    departments: DepartmentOption[];
    can: UserAbility;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: UserController.index.url(),
            },
            {
                title: 'Edit user',
                href: UserController.index.url(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link :href="UserController.index.url()">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <Heading
                :title="`Edit ${user.name}`"
                description="Update the account details and the roles it holds."
            />
        </div>

        <UserForm
            :user="props.user"
            :role-names="props.roleNames"
            :roles="props.roles"
            :departments="props.departments"
            :can="props.can"
        />
    </div>
</template>
