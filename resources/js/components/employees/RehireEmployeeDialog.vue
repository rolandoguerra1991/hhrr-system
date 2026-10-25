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
    started_on: today(),
    start_reason: '',
});

function submit(): void {
    form.post(EmployeeController.rehire.url(props.employeeId), {
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
                <DialogTitle>Rehire {{ employeeName }}?</DialogTitle>
                <DialogDescription>
                    This starts a new employment period with the same employee
                    number. A department assignment must be added separately.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="rehire_started_on">
                        New employment start date
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="rehire_started_on"
                        v-model="form.started_on"
                        type="date"
                        :max="today()"
                        required
                        :aria-invalid="Boolean(form.errors.started_on)"
                    />
                    <InputError :message="form.errors.started_on" />
                </div>

                <div class="grid gap-2">
                    <Label for="rehire_start_reason">
                        Start reason
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <textarea
                        id="rehire_start_reason"
                        v-model="form.start_reason"
                        rows="3"
                        required
                        maxlength="255"
                        :aria-invalid="Boolean(form.errors.start_reason)"
                        class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                    />
                    <InputError :message="form.errors.start_reason" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing ? 'Rehiring...' : 'Rehire employee'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
