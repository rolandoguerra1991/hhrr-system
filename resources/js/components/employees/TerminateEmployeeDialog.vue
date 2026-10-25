<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
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

const props = defineProps<{
    modelValue: boolean;
    employeeId: number;
    employeeName: string;
    departmentsManaged: string[];
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
    ended_on: today(),
    termination_reason: '',
});

function submit(): void {
    form.post(EmployeeController.terminate.url(props.employeeId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Terminate {{ employeeName }}?</DialogTitle>
                <DialogDescription>
                    This closes the current employment period, ends any current
                    assignment, and blocks the linked login account.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="departmentsManaged.length > 0"
                class="rounded-md border border-destructive/50 bg-destructive/5 p-3 text-sm"
                role="alert"
            >
                This employee manages
                {{ departmentsManaged.join(', ') }}. Reassign those department
                managers before termination.
            </div>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="termination_ended_on">
                        Termination date
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="termination_ended_on"
                        v-model="form.ended_on"
                        type="date"
                        :max="today()"
                        required
                        :aria-invalid="Boolean(form.errors.ended_on)"
                    />
                    <InputError :message="form.errors.ended_on" />
                </div>

                <div class="grid gap-2">
                    <Label for="termination_reason">
                        Reason
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <textarea
                        id="termination_reason"
                        v-model="form.termination_reason"
                        rows="3"
                        required
                        maxlength="2000"
                        :aria-invalid="Boolean(form.errors.termination_reason)"
                        class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                    />
                    <InputError :message="form.errors.termination_reason" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? 'Terminating...'
                                : 'Terminate employee'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
