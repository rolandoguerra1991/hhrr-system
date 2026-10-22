<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import PositionController from '@/actions/App/Http/Controllers/PositionController';
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
import type { DepartmentOption, PositionDetails } from '@/types';

const props = withDefaults(
    defineProps<{
        departmentOptions: DepartmentOption[];
        position?: PositionDetails;
        stickyActions?: boolean;
    }>(),
    {
        stickyActions: false,
    },
);

const isEditing = computed(() => props.position !== undefined);

const form = useForm({
    name: props.position?.name ?? '',
    department_id: props.position?.department_id ?? null,
});

const departmentId = computed({
    get: (): string =>
        form.department_id === null ? 'none' : String(form.department_id),
    set: (value: string): void => {
        form.department_id = value === 'none' ? null : Number(value);
    },
});

function optionLabel(option: DepartmentOption): string {
    return `${'\u2014 '.repeat(option.depth)}${option.name}`;
}

function submit(): void {
    if (props.position) {
        form.put(PositionController.update.url(props.position.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(PositionController.store.url());
}
</script>

<template>
    <form class="mx-auto w-full max-w-4xl space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Position details</CardTitle>
                <CardDescription>
                    The name is unique within the same department or among
                    company-wide positions.
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
                        data-test="position-name-input"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="department_id">Department</Label>
                    <Select v-model="departmentId">
                        <SelectTrigger
                            id="department_id"
                            class="w-full"
                            :aria-invalid="Boolean(form.errors.department_id)"
                            data-test="position-department-select"
                        >
                            <SelectValue placeholder="No department" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                No department (company-wide)
                            </SelectItem>
                            <SelectItem
                                v-for="option in departmentOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ optionLabel(option) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        Leave empty to make the position company-wide.
                    </p>
                    <InputError :message="form.errors.department_id" />
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
                <Link :href="PositionController.index.url()">Cancel</Link>
            </Button>
            <Button
                type="submit"
                :disabled="form.processing"
                :data-test="
                    isEditing
                        ? 'update-position-button'
                        : 'create-position-button'
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
                          : 'Create position'
                }}
            </Button>
        </div>
    </form>
</template>
