<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
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
import type { EmployeeFormDetails, EmployeeUserOption } from '@/types';

const props = withDefaults(
    defineProps<{
        users: EmployeeUserOption[];
        employee?: EmployeeFormDetails;
        stickyActions?: boolean;
    }>(),
    {
        stickyActions: false,
    },
);

const isEditing = computed(() => props.employee !== undefined);

function today(): string {
    const date = new Date();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

const form = useForm({
    first_name: props.employee?.first_name ?? '',
    last_name: props.employee?.last_name ?? '',
    work_email: props.employee?.work_email ?? '',
    user_id: props.employee?.user_id ?? (null as number | null),
    started_on: today(),
    reason: '',
});

const userId = computed({
    get: (): string => (form.user_id === null ? 'none' : String(form.user_id)),
    set: (value: string): void => {
        form.user_id = value === 'none' ? null : Number(value);
    },
});

function submit(): void {
    if (props.employee) {
        form.put(EmployeeController.update.url(props.employee.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(EmployeeController.store.url());
}
</script>

<template>
    <form class="mx-auto w-full max-w-4xl space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Employee details</CardTitle>
                <CardDescription>
                    Employee records contain basic identity and employment
                    information only.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-5 md:grid-cols-2">
                <div v-if="employee" class="grid gap-2 md:col-span-2">
                    <Label for="employee_number">Employee number</Label>
                    <Input
                        id="employee_number"
                        :model-value="employee.employee_number"
                        readonly
                        aria-readonly="true"
                        data-test="employee-number"
                    />
                    <p class="text-sm text-muted-foreground">
                        Employee numbers are permanent and cannot be changed.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="first_name">
                        First name
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="first_name"
                        v-model="form.first_name"
                        autocomplete="given-name"
                        required
                        :aria-invalid="Boolean(form.errors.first_name)"
                        data-test="employee-first-name"
                    />
                    <InputError :message="form.errors.first_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_name">
                        Last name
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="last_name"
                        v-model="form.last_name"
                        autocomplete="family-name"
                        required
                        :aria-invalid="Boolean(form.errors.last_name)"
                        data-test="employee-last-name"
                    />
                    <InputError :message="form.errors.last_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="work_email">Work email</Label>
                    <Input
                        id="work_email"
                        v-model="form.work_email"
                        type="email"
                        autocomplete="email"
                        :aria-invalid="Boolean(form.errors.work_email)"
                        data-test="employee-work-email"
                    />
                    <InputError :message="form.errors.work_email" />
                </div>

                <div class="grid gap-2">
                    <Label for="user_id">Login account</Label>
                    <Select v-model="userId">
                        <SelectTrigger
                            id="user_id"
                            class="w-full"
                            :aria-invalid="Boolean(form.errors.user_id)"
                            data-test="employee-user-select"
                        >
                            <SelectValue placeholder="No linked account" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No account</SelectItem>
                            <SelectItem
                                v-for="userOption in users"
                                :key="userOption.id"
                                :value="String(userOption.id)"
                            >
                                {{ userOption.name }} ({{ userOption.email }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        Each login account can be linked to one employee.
                    </p>
                    <InputError :message="form.errors.user_id" />
                </div>

                <div v-if="!employee" class="grid gap-2">
                    <Label for="started_on">
                        Employment start date
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="started_on"
                        v-model="form.started_on"
                        type="date"
                        :max="today()"
                        required
                        :aria-invalid="Boolean(form.errors.started_on)"
                        data-test="employee-start-date"
                    />
                    <InputError :message="form.errors.started_on" />
                </div>

                <div v-if="employee" class="grid gap-2 md:col-span-2">
                    <Label for="reason">
                        Reason for changes
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <textarea
                        id="reason"
                        v-model="form.reason"
                        rows="3"
                        required
                        maxlength="500"
                        :aria-invalid="Boolean(form.errors.reason)"
                        class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                        data-test="employee-edit-reason"
                    />
                    <InputError :message="form.errors.reason" />
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
                <Link
                    :href="
                        employee
                            ? EmployeeController.show.url(employee.id)
                            : EmployeeController.index.url()
                    "
                >
                    Cancel
                </Link>
            </Button>
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="save-employee-button"
            >
                <LoaderCircle
                    v-if="form.processing"
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                {{
                    form.processing
                        ? 'Saving...'
                        : employee
                          ? 'Save changes'
                          : 'Create employee'
                }}
            </Button>
        </div>
    </form>
</template>
