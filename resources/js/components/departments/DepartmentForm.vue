<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/DepartmentController';
import InputError from '@/components/InputError.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    DepartmentDetails,
    DepartmentManagerOption,
    DepartmentOption,
} from '@/types';

const props = withDefaults(
    defineProps<{
        parentOptions: DepartmentOption[];
        managers: DepartmentManagerOption[];
        department?: DepartmentDetails;
        stickyActions?: boolean;
    }>(),
    {
        stickyActions: false,
    },
);

const isEditing = computed(() => props.department !== undefined);

const form = useForm({
    name: props.department?.name ?? '',
    parent_id: props.department?.parent_id ?? null,
    manager_id: props.department?.manager_id ?? null,
});

const parentId = computed({
    get: (): string =>
        form.parent_id === null ? 'none' : String(form.parent_id),
    set: (value: string): void => {
        form.parent_id = value === 'none' ? null : Number(value);
    },
});

const managerId = computed({
    get: (): string =>
        form.manager_id === null ? 'none' : String(form.manager_id),
    set: (value: string): void => {
        form.manager_id = value === 'none' ? null : Number(value);
    },
});

function optionLabel(option: DepartmentOption): string {
    return `${'\u2014 '.repeat(option.depth)}${option.name}`;
}

function submit(): void {
    if (props.department) {
        form.put(DepartmentController.update.url(props.department.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(DepartmentController.store.url());
}
</script>

<template>
    <form class="mx-auto w-full max-w-4xl space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Department details</CardTitle>
                <CardDescription>
                    The name is unique among departments that share the same
                    parent.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-5 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="name">
                        Name
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        name="name"
                        autocomplete="off"
                        required
                        :aria-invalid="Boolean(form.errors.name)"
                        data-test="department-name-input"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="parent_id">Parent department</Label>
                    <Select v-model="parentId">
                        <SelectTrigger
                            id="parent_id"
                            class="w-full"
                            :aria-invalid="Boolean(form.errors.parent_id)"
                            data-test="department-parent-select"
                        >
                            <SelectValue placeholder="No parent" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                No parent (top level)
                            </SelectItem>
                            <SelectItem
                                v-for="option in parentOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ optionLabel(option) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        Leave empty to make the department top level.
                    </p>
                    <InputError :message="form.errors.parent_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="manager_id">Manager</Label>
                    <Select v-model="managerId">
                        <SelectTrigger
                            id="manager_id"
                            class="w-full"
                            :aria-invalid="Boolean(form.errors.manager_id)"
                            data-test="department-manager-select"
                        >
                            <SelectValue placeholder="No manager" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No manager</SelectItem>
                            <SelectItem
                                v-for="manager in managers"
                                :key="manager.id"
                                :value="String(manager.id)"
                            >
                                {{ manager.name }} ({{
                                    manager.employee_number
                                }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        Optional. Only active employees can be selected.
                    </p>
                    <InputError :message="form.errors.manager_id" />
                </div>
            </CardContent>
        </Card>

        <div
            class="flex items-center justify-end gap-3"
            :class="{
                'sticky bottom-0 z-10 -mx-4 bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80':
                    stickyActions,
            }"
        >
            <Button variant="secondary" as-child>
                <Link :href="DepartmentController.index.url()">Cancel</Link>
            </Button>
            <Button
                type="submit"
                :disabled="form.processing"
                :data-test="
                    isEditing
                        ? 'update-department-button'
                        : 'create-department-button'
                "
            >
                <LoaderCircle
                    v-if="form.processing"
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                {{
                    form.processing
                        ? isEditing
                            ? 'Saving...'
                            : 'Creating...'
                        : isEditing
                          ? 'Save changes'
                          : 'Create department'
                }}
            </Button>
        </div>
    </form>
</template>
