<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, ShieldCheck, Trash2, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/Heading.vue';
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
import type { Role } from '@/types';

const props = defineProps<{
    roles: Role[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Roles',
                href: RoleController.index.url(),
            },
        ],
    },
});

const search = ref('');
const roleToDelete = ref<Role | null>(null);
const deleteDialogOpen = ref(false);

const page = usePage();

const canCreateRole = computed(() => page.props.auth.can.roles.create);

const filteredRoles = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return props.roles;
    }

    return props.roles.filter((role) =>
        role.name.toLowerCase().includes(query),
    );
});

function askToDelete(role: Role): void {
    roleToDelete.value = role;
    deleteDialogOpen.value = true;
}

function destroy(): void {
    if (roleToDelete.value === null) {
        return;
    }

    router.delete(RoleController.destroy.url(roleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            roleToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Roles" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Roles"
                description="Manage the roles that group permissions in your application."
            />

            <Button v-if="canCreateRole" as-child>
                <Link :href="RoleController.create.url()">
                    <Plus class="size-4" />
                    New role
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
                placeholder="Search roles..."
                aria-label="Search roles"
                data-test="search-roles"
            />
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
        >
            <p aria-live="polite">
                Showing {{ filteredRoles.length }} of {{ roles.length }} roles
            </p>
            <Button
                v-if="search"
                type="button"
                variant="ghost"
                size="sm"
                @click="search = ''"
            >
                Clear search
            </Button>
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Role</TableHead>
                        <TableHead class="hidden sm:table-cell">
                            Permissions
                        </TableHead>
                        <TableHead class="hidden sm:table-cell">
                            Users
                        </TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="role in filteredRoles" :key="role.id">
                        <TableCell>
                            <div class="flex items-center gap-2 font-medium">
                                <ShieldCheck
                                    class="size-4 text-muted-foreground"
                                />
                                {{ role.name }}
                                <Badge
                                    v-if="role.is_protected"
                                    variant="secondary"
                                >
                                    Protected
                                </Badge>
                            </div>
                            <p
                                class="mt-1 text-xs text-muted-foreground sm:hidden"
                            >
                                {{
                                    role.is_protected
                                        ? 'All permissions'
                                        : `${role.permissions_count} permissions`
                                }}
                                <span aria-hidden="true">·</span>
                                {{ role.users_count }}
                                {{ role.users_count === 1 ? 'user' : 'users' }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden sm:table-cell">
                            <Badge variant="outline">
                                {{
                                    role.is_protected
                                        ? 'All'
                                        : role.permissions_count
                                }}
                            </Badge>
                        </TableCell>
                        <TableCell class="hidden sm:table-cell">
                            <span
                                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Users class="size-4" />
                                {{ role.users_count }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="role.can.update"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link
                                        :href="RoleController.edit.url(role.id)"
                                    >
                                        Edit
                                    </Link>
                                </Button>

                                <Button
                                    v-if="role.can.delete"
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="role.users_count > 0"
                                    :title="
                                        role.users_count > 0
                                            ? 'Remove the assigned users before deleting this role'
                                            : 'Delete role'
                                    "
                                    :aria-label="`Delete role ${role.name}`"
                                    data-test="delete-role-button"
                                    @click="askToDelete(role)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="filteredRoles.length === 0">
                        <TableCell
                            colspan="4"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            {{
                                roles.length === 0
                                    ? 'No roles yet.'
                                    : 'No roles match your search.'
                            }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle> Delete {{ roleToDelete?.name }}? </DialogTitle>
                <DialogDescription>
                    This action cannot be undone. Users will lose the
                    permissions granted by this role.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    data-test="confirm-delete-role-button"
                    @click="destroy"
                >
                    Delete role
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
