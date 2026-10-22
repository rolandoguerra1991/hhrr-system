<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Search, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PermissionChecklist from '@/components/PermissionChecklist.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import type { PermissionOption, RoleUser } from '@/types';

const props = defineProps<{
    role: {
        id: number;
        name: string;
        users_count: number;
    };
    rolePermissions: string[];
    permissions: PermissionOption[];
    users: RoleUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Roles',
                href: RoleController.index.url(),
            },
            {
                title: 'Edit role',
                href: RoleController.index.url(),
            },
        ],
    },
});

const form = useForm({
    name: props.role.name,
    permissions: [...props.rolePermissions],
});

const userSearch = ref('');
const filteredUsers = computed(() => {
    const query = userSearch.value.trim().toLowerCase();

    if (query === '') {
        return props.users;
    }

    return props.users.filter(
        (user) =>
            user.name.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query),
    );
});

function submit(): void {
    form.put(RoleController.update.url(props.role.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Edit ${role.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link :href="RoleController.index.url()">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <Heading
                :title="`Edit ${role.name}`"
                description="Rename the role and adjust the permissions it grants."
            />
        </div>

        <form
            class="mx-auto w-full max-w-4xl space-y-6"
            @submit.prevent="submit"
        >
            <Card class="mx-auto w-full max-w-4xl">
                <CardHeader>
                    <CardTitle>Role details</CardTitle>
                    <CardDescription>
                        A descriptive name makes the role easy to recognise.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        name="name"
                        autocomplete="off"
                        data-test="role-name-input"
                    />
                    <InputError :message="form.errors.name" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Permissions</CardTitle>
                    <CardDescription>
                        Select the permissions this role should grant.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <PermissionChecklist
                        v-model="form.permissions"
                        :permissions="permissions"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.permissions"
                    />
                </CardContent>
            </Card>

            <div class="flex items-center justify-end gap-3">
                <Button variant="secondary" as-child>
                    <Link :href="RoleController.index.url()">Cancel</Link>
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="update-role-button"
                >
                    Save changes
                </Button>
            </div>
        </form>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="size-4" />
                    Assigned users
                    <Badge variant="outline">{{ role.users_count }}</Badge>
                </CardTitle>
                <CardDescription>
                    Users that currently have this role. Manage their roles from
                    the user's profile.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    v-if="users.length === 0"
                    class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    No users have this role yet.
                </div>
                <div v-else class="grid gap-3">
                    <div v-if="users.length > 5" class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="userSearch"
                            class="pl-9"
                            placeholder="Search assigned users..."
                            aria-label="Search assigned users"
                        />
                    </div>

                    <p
                        v-if="users.length > 5"
                        class="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        Showing {{ filteredUsers.length }} of
                        {{ users.length }} users
                    </p>

                    <div
                        v-if="filteredUsers.length === 0"
                        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        No assigned users match your search.
                    </div>
                    <ScrollArea v-else class="max-h-72 rounded-md">
                        <ul class="divide-y pr-3">
                            <li
                                v-for="user in filteredUsers"
                                :key="user.id"
                                class="flex flex-col gap-1 py-2.5 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                            >
                                <span class="text-sm font-medium">{{
                                    user.name
                                }}</span>
                                <span
                                    class="text-xs break-all text-muted-foreground"
                                >
                                    {{ user.email }}
                                </span>
                            </li>
                        </ul>
                        <template #scrollbar>
                            <ScrollBar orientation="vertical" />
                        </template>
                    </ScrollArea>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
