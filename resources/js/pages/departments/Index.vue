<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Building2, CornerDownRight, Plus, Trash2, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/DepartmentController';
import Heading from '@/components/Heading.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Department } from '@/types';

const props = defineProps<{
    departments: Department[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Departments',
                href: DepartmentController.index.url(),
            },
        ],
    },
});

const page = usePage();
const canCreateDepartment = computed(
    () => page.props.auth.can.departments.create,
);

const departmentToDelete = ref<Department | null>(null);
const deleteDialogOpen = ref(false);

function isInUse(department: Department): boolean {
    return department.children_count > 0 || department.users_count > 0;
}

function inUseReason(department: Department): string | null {
    if (department.children_count > 0 && department.users_count > 0) {
        return 'Move or delete the child departments and reassign the users first';
    }

    if (department.children_count > 0) {
        return 'Move or delete the child departments first';
    }

    if (department.users_count > 0) {
        return 'Reassign the users to another department first';
    }

    return null;
}

function askToDelete(department: Department): void {
    if (isInUse(department)) {
        return;
    }

    departmentToDelete.value = department;
    deleteDialogOpen.value = true;
}

function destroy(): void {
    if (departmentToDelete.value === null) {
        return;
    }

    router.delete(
        DepartmentController.destroy.url(departmentToDelete.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                departmentToDelete.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Departments" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Departments"
                description="Organize the company into the structure the rest of the HR modules will use."
            />

            <Button v-if="canCreateDepartment" as-child>
                <Link :href="DepartmentController.create.url()">
                    <Plus class="size-4" />
                    New department
                </Link>
            </Button>
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Department</TableHead>
                        <TableHead class="hidden sm:table-cell">
                            Manager
                        </TableHead>
                        <TableHead class="hidden sm:table-cell">
                            Members
                        </TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="department in props.departments"
                        :key="department.id"
                    >
                        <TableCell>
                            <div
                                class="flex items-center gap-2 font-medium"
                                :style="{
                                    paddingLeft: `${department.depth * 1.5}rem`,
                                }"
                            >
                                <CornerDownRight
                                    v-if="department.depth > 0"
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Building2
                                    v-else
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                {{ department.name }}
                            </div>
                            <p
                                class="mt-1 text-xs text-muted-foreground sm:hidden"
                                :style="{
                                    paddingLeft: `${department.depth * 1.5}rem`,
                                }"
                            >
                                {{ department.manager_name ?? 'No manager' }}
                                <span aria-hidden="true">·</span>
                                {{ department.users_count }}
                                {{
                                    department.users_count === 1
                                        ? 'member'
                                        : 'members'
                                }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden sm:table-cell">
                            <span
                                v-if="department.manager_name"
                                class="text-sm"
                            >
                                {{ department.manager_name }}
                            </span>
                            <span v-else class="text-sm text-muted-foreground">
                                No manager
                            </span>
                        </TableCell>
                        <TableCell class="hidden sm:table-cell">
                            <span
                                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Users class="size-4" />
                                {{ department.users_count }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="department.can.update"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            DepartmentController.edit.url(
                                                department.id,
                                            )
                                        "
                                    >
                                        Edit
                                    </Link>
                                </Button>

                                <Button
                                    v-if="department.can.delete"
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="isInUse(department)"
                                    :title="
                                        inUseReason(department) ??
                                        'Delete department'
                                    "
                                    :aria-label="`Delete department ${department.name}`"
                                    data-test="delete-department-button"
                                    @click="askToDelete(department)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="props.departments.length === 0">
                        <TableCell
                            colspan="4"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No departments yet.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    Delete {{ departmentToDelete?.name }}?
                </DialogTitle>
                <DialogDescription>
                    This action cannot be undone. The department disappears from
                    the tree.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    data-test="confirm-delete-department-button"
                    @click="destroy"
                >
                    Delete department
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
