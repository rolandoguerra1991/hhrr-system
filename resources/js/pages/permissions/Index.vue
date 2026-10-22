<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PermissionController from '@/actions/App/Http/Controllers/PermissionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PermissionCatalogueItem } from '@/types';

const props = defineProps<{
    permissions: PermissionCatalogueItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Permissions',
                href: PermissionController.index.url(),
            },
        ],
    },
});

const search = ref('');
const createDialogOpen = ref(false);

const page = usePage();

const canCreatePermission = computed(
    () => page.props.auth.can.permissions.create,
);

const createForm = useForm({
    name: '',
});

const permissionToDelete = ref<PermissionCatalogueItem | null>(null);
const deleteDialogOpen = ref(false);

watch(createDialogOpen, (open) => {
    if (!open) {
        createForm.reset();
        createForm.clearErrors();
    }
});

const filteredPermissions = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return props.permissions;
    }

    return props.permissions.filter(
        (permission) =>
            permission.name.toLowerCase().includes(query) ||
            permission.label.toLowerCase().includes(query) ||
            permission.group_label.toLowerCase().includes(query),
    );
});

const groups = computed(() => {
    const grouped = new Map<
        string,
        { key: string; label: string; items: PermissionCatalogueItem[] }
    >();

    for (const permission of filteredPermissions.value) {
        const group = grouped.get(permission.group);

        if (group) {
            group.items.push(permission);

            continue;
        }

        grouped.set(permission.group, {
            key: permission.group,
            label: permission.group_label,
            items: [permission],
        });
    }

    return Array.from(grouped.values());
});

function storePermission(): void {
    createForm.post(PermissionController.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createDialogOpen.value = false;
        },
    });
}

function askToDelete(permission: PermissionCatalogueItem): void {
    permissionToDelete.value = permission;
    deleteDialogOpen.value = true;
}

function destroyPermission(): void {
    if (permissionToDelete.value === null) {
        return;
    }

    router.delete(
        PermissionController.destroy.url(permissionToDelete.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                permissionToDelete.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Permissions" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Permissions"
                description="The catalogue of permissions that can be granted to roles."
            />

            <Dialog v-if="canCreatePermission" v-model:open="createDialogOpen">
                <DialogTrigger as-child>
                    <Button data-test="new-permission-button">
                        <Plus class="size-4" />
                        New permission
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create permission</DialogTitle>
                        <DialogDescription>
                            Use the resource.action convention, for example
                            <code>reports.export</code>.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="createForm.name"
                            name="name"
                            placeholder="reports.export"
                            autocomplete="off"
                            required
                            :aria-invalid="Boolean(createForm.errors.name)"
                            data-test="permission-name-input"
                            @keydown.enter.prevent="storePermission"
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary"> Cancel </Button>
                        </DialogClose>
                        <Button
                            :disabled="createForm.processing"
                            data-test="create-permission-button"
                            @click="storePermission"
                        >
                            {{
                                createForm.processing
                                    ? 'Creating...'
                                    : 'Create permission'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <div class="relative w-full sm:max-w-xs">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-9"
                placeholder="Search permissions..."
                aria-label="Search permissions"
                data-test="search-permissions"
            />
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
        >
            <p aria-live="polite">
                Showing {{ filteredPermissions.length }} of
                {{ permissions.length }} permissions
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

        <div
            v-if="groups.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            {{
                permissions.length === 0
                    ? 'No permissions yet.'
                    : 'No permissions match your search.'
            }}
        </div>

        <div v-else class="grid gap-4 lg:grid-cols-2">
            <Card v-for="group in groups" :key="group.key">
                <CardHeader>
                    <CardTitle>{{ group.label }}</CardTitle>
                    <CardDescription>
                        {{ group.items.length }} permission(s) in this group.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-1">
                    <div
                        v-for="permission in group.items"
                        :key="permission.name"
                        class="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-muted/50"
                    >
                        <KeyRound
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">
                                {{ permission.label }}
                            </p>
                            <p
                                class="truncate font-mono text-xs text-muted-foreground"
                            >
                                {{ permission.name }}
                            </p>
                        </div>

                        <Badge variant="outline">
                            {{ permission.roles_count }}
                            {{
                                permission.roles_count === 1 ? 'role' : 'roles'
                            }}
                        </Badge>

                        <Button
                            v-if="permission.can.delete"
                            variant="ghost"
                            size="icon-sm"
                            title="Delete permission"
                            :aria-label="`Delete permission ${permission.name}`"
                            data-test="delete-permission-button"
                            @click="askToDelete(permission)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    Delete {{ permissionToDelete?.name }}?
                </DialogTitle>
                <DialogDescription>
                    Roles that include this permission will lose it. This action
                    cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    data-test="confirm-delete-permission-button"
                    @click="destroyPermission"
                >
                    Delete permission
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
