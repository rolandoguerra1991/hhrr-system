<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeAssignmentController from '@/actions/App/Http/Controllers/EmployeeAssignmentController';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EmployeeDepartmentOption, EmployeePositionOption } from '@/types';

const props = defineProps<{
    modelValue: boolean;
    employeeId: number;
    employeeName: string;
    departmentOptions: EmployeeDepartmentOption[];
    positionOptions: EmployeePositionOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const open = computed({
    get: (): boolean => props.modelValue,
    set: (value: boolean): void => emit('update:modelValue', value),
});

function today(): string {
    const date = new Date();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

const form = useForm({
    department_id: null as number | null,
    position_id: null as number | null,
    effective_from: today(),
    reason: '',
});

const departmentId = computed({
    get: (): string =>
        form.department_id === null ? 'none' : String(form.department_id),
    set: (value: string): void => {
        form.department_id = value === 'none' ? null : Number(value);

        if (
            form.position_id !== null &&
            !props.positionOptions.some(
                (position) =>
                    position.id === form.position_id &&
                    (position.department_id === null ||
                        position.department_id === form.department_id),
            )
        ) {
            form.position_id = null;
        }
    },
});

const positionId = computed({
    get: (): string =>
        form.position_id === null ? 'none' : String(form.position_id),
    set: (value: string): void => {
        form.position_id = value === 'none' ? null : Number(value);
    },
});

const availablePositions = computed(() =>
    props.positionOptions.filter(
        (position) =>
            position.department_id === null ||
            position.department_id === form.department_id,
    ),
);

function submit(): void {
    form.post(
        EmployeeAssignmentController.store.url({
            employee: props.employeeId,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                form.reset();
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Assign {{ employeeName }}</DialogTitle>
                <DialogDescription>
                    Create a new effective-dated department assignment. The
                    previous assignment remains in the employee's history.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="assignment_department_id">
                        Department
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Select v-model="departmentId">
                        <SelectTrigger
                            id="assignment_department_id"
                            class="w-full"
                            :aria-invalid="Boolean(form.errors.department_id)"
                        >
                            <SelectValue placeholder="Choose a department" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="department in departmentOptions"
                                :key="department.id"
                                :value="String(department.id)"
                            >
                                {{ department.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.department_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="assignment_position_id">Position</Label>
                    <Select v-model="positionId">
                        <SelectTrigger
                            id="assignment_position_id"
                            class="w-full"
                            :aria-invalid="Boolean(form.errors.position_id)"
                        >
                            <SelectValue placeholder="No position" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No position</SelectItem>
                            <SelectItem
                                v-for="position in availablePositions"
                                :key="position.id"
                                :value="String(position.id)"
                            >
                                {{ position.name }}
                                ({{
                                    position.department_name ?? 'Company-wide'
                                }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.position_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="assignment_effective_from">
                        Effective date
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="assignment_effective_from"
                        v-model="form.effective_from"
                        type="date"
                        :max="today()"
                        required
                        :aria-invalid="Boolean(form.errors.effective_from)"
                    />
                    <InputError :message="form.errors.effective_from" />
                </div>

                <div class="grid gap-2">
                    <Label for="assignment_reason">
                        Reason
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <textarea
                        id="assignment_reason"
                        v-model="form.reason"
                        rows="3"
                        required
                        maxlength="500"
                        :aria-invalid="Boolean(form.errors.reason)"
                        class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save assignment' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
