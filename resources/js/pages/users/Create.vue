<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import UserForm from '@/components/users/UserForm.vue';
import { Button } from '@/components/ui/button';
import type { UserAbility, UserRoleOption } from '@/types';

const props = defineProps<{
    roles: UserRoleOption[];
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
                title: 'New user',
                href: UserController.create.url(),
            },
        ],
    },
});
</script>

<template>
    <Head title="New user" />

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
                title="New user"
                description="Create an account and choose the roles it should have."
            />
        </div>

        <UserForm :roles="props.roles" :can="props.can" sticky-actions />
    </div>
</template>
