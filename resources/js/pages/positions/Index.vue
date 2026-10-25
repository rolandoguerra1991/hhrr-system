<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BriefcaseBusiness, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PositionController from '@/actions/App/Http/Controllers/PositionController';
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
import type { Position } from '@/types';

const props = defineProps<{
    positions: Position[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Positions',
                href: PositionController.index.url(),
            },
        ],
    },
});

const page = usePage();
const canCreatePosition = computed(() => page.props.auth.can.positions.create);

const positionToDelete = ref<Position | null>(null);
const deleteDialogOpen = ref(false);

function askToDelete(position: Position): void {
    positionToDelete.value = position;
    deleteDialogOpen.value = true;
}

function destroy(): void {
    if (positionToDelete.value === null) {
        return;
    }

    router.delete(PositionController.destroy.url(positionToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            positionToDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Positions" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Positions"
                description="Catalog the job titles the company uses, optionally scoped to a department."
            />

            <Button v-if="canCreatePosition" as-child>
                <Link :href="PositionController.create.url()">
                    <Plus class="size-4" />
                    New position
                </Link>
            </Button>
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Position</TableHead>
                        <TableHead class="hidden sm:table-cell">
                            Department
                        </TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="position in props.positions"
                        :key="position.id"
                    >
                        <TableCell>
                            <div class="flex items-center gap-2 font-medium">
                                <BriefcaseBusiness
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                {{ position.name }}
                            </div>
                            <p
                                class="mt-1 text-xs text-muted-foreground sm:hidden"
                            >
                                {{ position.department_name ?? 'Company-wide' }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden sm:table-cell">
                            <span
                                v-if="position.department_name"
                                class="text-sm"
                            >
                                {{ position.department_name }}
                            </span>
                            <span v-else class="text-sm text-muted-foreground">
                                Company-wide
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <span
                                    v-if="position.is_in_use"
                                    class="self-center text-xs text-muted-foreground"
                                >
                                    In use ({{ position.assignments_count }})
                                </span>
                                <Button
                                    v-if="position.can.update"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            PositionController.edit.url(
                                                position.id,
                                            )
                                        "
                                    >
                                        Edit
                                    </Link>
                                </Button>

                                <Button
                                    v-if="
                                        position.can.delete &&
                                        !position.is_in_use
                                    "
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Delete position"
                                    :aria-label="`Delete position ${position.name}`"
                                    data-test="delete-position-button"
                                    @click="askToDelete(position)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="props.positions.length === 0">
                        <TableCell
                            colspan="3"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No positions yet.
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
                    Delete {{ positionToDelete?.name }}?
                </DialogTitle>
                <DialogDescription>
                    This action cannot be undone. The position disappears from
                    the catalogue.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    data-test="confirm-delete-position-button"
                    @click="destroy"
                >
                    Delete position
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
