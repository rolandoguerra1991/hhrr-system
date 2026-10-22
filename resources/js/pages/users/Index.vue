<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, ShieldCheck, Trash2, UserRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ManagedUser, Paginated } from '@/types';

const props = defineProps<{
    users: Paginated<ManagedUser>;
    filters: {
        search: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: UserController.index.url(),
            },
        ],
    },
});

const page = usePage();
const canCreateUser = computed(() => page.props.auth.can.users.create);

const search = ref(props.filters.search);
const userToDelete = ref<ManagedUser | null>(null);
const deleteDialogOpen = ref(false);

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        router.get(
            UserController.index.url({
                query: { search: search.value.trim() || undefined },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function askToDelete(user: ManagedUser): void {
    userToDelete.value = user;
    deleteDialogOpen.value = true;
}

function destroy(): void {
    if (userToDelete.value === null) {
        return;
    }

    router.delete(UserController.destroy.url(userToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            userToDelete.value = null;
        },
    });
}

function lockedReason(user: ManagedUser): string | null {
    if (user.can.update || user.can.delete) {
        return null;
    }

    if (user.is_protected) {
        return 'Managed from the application configuration';
    }

    if (user.is_self) {
        return 'You manage your own account from the settings';
    }

    return null;
}
</script>

<template>
    <Head title="Users" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Users"
                description="Manage the accounts that can sign in to your application."
            />

            <Button v-if="canCreateUser" as-child>
                <Link :href="UserController.create.url()">
                    <Plus class="size-4" />
                    New user
                </Link>
            </Button>
        </div>

        <div class="relative w-full sm:max-w-xs">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-9"
                placeholder="Search users..."
                data-test="search-users"
            />
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>User</TableHead>
                        <TableHead>Roles</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in users.data" :key="user.id">
                        <TableCell>
                            <div class="flex items-center gap-2 font-medium">
                                <UserRound
                                    class="size-4 text-muted-foreground"
                                />
                                {{ user.name }}
                                <Badge
                                    v-if="user.is_protected"
                                    variant="secondary"
                                    title="Managed from the application configuration"
                                >
                                    Protected
                                </Badge>
                                <Badge
                                    v-else-if="user.is_self"
                                    variant="outline"
                                >
                                    You
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap items-center gap-1">
                                <Badge
                                    v-for="role in user.roles"
                                    :key="role"
                                    variant="outline"
                                >
                                    {{ role }}
                                </Badge>
                                <span
                                    v-if="user.roles.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    No roles
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="user.can.update"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link
                                        :href="UserController.edit.url(user.id)"
                                    >
                                        Edit
                                    </Link>
                                </Button>

                                <Button
                                    v-if="user.can.delete"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Delete user"
                                    data-test="delete-user-button"
                                    @click="askToDelete(user)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>

                                <span
                                    v-if="lockedReason(user)"
                                    class="flex items-center px-2 text-muted-foreground"
                                    :title="lockedReason(user) ?? undefined"
                                >
                                    <ShieldCheck class="size-4" />
                                </span>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="users.data.length === 0">
                        <TableCell
                            colspan="3"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No users found.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination
            :links="users.links"
            :from="users.from"
            :to="users.to"
            :total="users.total"
        />
    </div>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle> Delete {{ userToDelete?.name }}? </DialogTitle>
                <DialogDescription>
                    This action cannot be undone. The account will lose access
                    to the application immediately.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    data-test="confirm-delete-user-button"
                    @click="destroy"
                >
                    Delete user
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
