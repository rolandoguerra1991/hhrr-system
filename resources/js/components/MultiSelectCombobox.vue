<script setup lang="ts">
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed, useId } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
    ComboboxViewport,
} from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import type { ComboboxOption } from '@/types';

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        options: ComboboxOption[];
        label: string;
        hint?: string;
        placeholder?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        noOptionsText?: string;
        id?: string;
        testId?: string;
        invalid?: boolean;
        disabled?: boolean;
        maxDisplayed?: number;
    }>(),
    {
        placeholder: 'Select...',
        searchPlaceholder: 'Search...',
        emptyText: 'No options match your search.',
        noOptionsText: 'No options available.',
        testId: 'combobox',
        invalid: false,
        disabled: false,
        maxDisplayed: 2,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const generatedId = useId();
const fieldId = computed(() => props.id ?? generatedId);
const hintId = computed(() => `${fieldId.value}-hint`);

const selectedOptions = computed<ComboboxOption[]>({
    get: () =>
        props.options.filter((option) =>
            props.modelValue.includes(option.value),
        ),
    set: (options) => {
        emit(
            'update:modelValue',
            options.map((option) => option.value),
        );
    },
});

const triggerLabel = computed(() => {
    const labels = selectedOptions.value.map((option) => option.label);

    if (labels.length === 0) {
        return props.placeholder;
    }

    if (labels.length <= props.maxDisplayed) {
        return labels.join(', ');
    }

    return `${labels.slice(0, props.maxDisplayed).join(', ')} +${labels.length - props.maxDisplayed} more`;
});

function isSelected(value: string): boolean {
    return props.modelValue.includes(value);
}
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center justify-between gap-3">
            <Label :for="fieldId">{{ label }}</Label>
            <span
                class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                aria-live="polite"
            >
                <slot name="count" :count="selectedOptions.length">
                    {{ selectedOptions.length }} selected
                </slot>
            </span>
        </div>

        <Combobox
            v-model="selectedOptions"
            multiple
            by="value"
            :disabled="disabled"
        >
            <ComboboxAnchor as-child>
                <ComboboxTrigger as-child>
                    <Button
                        :id="fieldId"
                        type="button"
                        variant="outline"
                        role="combobox"
                        class="w-full justify-between font-normal"
                        :disabled="disabled"
                        :aria-invalid="invalid"
                        :aria-describedby="hint ? hintId : undefined"
                        :data-test="`${testId}-trigger`"
                    >
                        <span
                            class="truncate"
                            :class="{
                                'text-muted-foreground':
                                    selectedOptions.length === 0,
                            }"
                        >
                            {{ triggerLabel }}
                        </span>
                        <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
                    </Button>
                </ComboboxTrigger>
            </ComboboxAnchor>

            <ComboboxList
                class="w-(--reka-combobox-trigger-width)"
                align="start"
            >
                <ComboboxInput
                    :placeholder="searchPlaceholder"
                    :aria-label="searchPlaceholder"
                    :data-test="`${testId}-search-input`"
                />
                <ComboboxViewport>
                    <div
                        v-if="options.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ noOptionsText }}
                    </div>
                    <template v-else>
                        <ComboboxEmpty>{{ emptyText }}</ComboboxEmpty>
                        <ComboboxGroup>
                            <ComboboxItem
                                v-for="option in options"
                                :key="option.value"
                                :value="option"
                                :disabled="option.disabled"
                                :data-test="`${testId}-option-${option.value}`"
                            >
                                <div
                                    class="pointer-events-none size-4 shrink-0 rounded-[4px] border border-input transition-all select-none data-[selected=true]:border-primary data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground *:[svg]:opacity-0 data-[selected=true]:*:[svg]:opacity-100"
                                    :data-selected="isSelected(option.value)"
                                >
                                    <Check class="size-3.5 text-current" />
                                </div>

                                <span
                                    class="truncate text-sm font-medium"
                                    :class="{
                                        'text-muted-foreground':
                                            option.disabled,
                                    }"
                                >
                                    {{ option.label }}
                                </span>

                                <span
                                    v-if="option.hint"
                                    class="ml-auto shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ option.hint }}
                                </span>
                            </ComboboxItem>
                        </ComboboxGroup>
                    </template>
                </ComboboxViewport>
            </ComboboxList>
        </Combobox>

        <p v-if="hint" :id="hintId" class="text-sm text-muted-foreground">
            {{ hint }}
        </p>

        <slot />
    </div>
</template>
