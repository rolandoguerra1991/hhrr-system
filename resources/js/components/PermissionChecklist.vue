<script setup lang="ts">
import { ChevronDown, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import type { PermissionOption } from '@/types';

type PermissionGroup = {
    key: string;
    label: string;
    items: PermissionOption[];
};

const props = withDefaults(
    defineProps<{
        permissions: PermissionOption[];
        modelValue: string[];
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const search = ref('');
const collapsedGroups = ref<string[]>([]);

const selected = computed(() => new Set(props.modelValue));

const groups = computed<PermissionGroup[]>(() => {
    const grouped = new Map<string, PermissionGroup>();

    for (const permission of props.permissions) {
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

const filteredGroups = computed<PermissionGroup[]>(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return groups.value;
    }

    return groups.value
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (permission) =>
                    permission.name.toLowerCase().includes(query) ||
                    permission.label.toLowerCase().includes(query) ||
                    group.label.toLowerCase().includes(query),
            ),
        }))
        .filter((group) => group.items.length > 0);
});

const visiblePermissionNames = computed(() =>
    filteredGroups.value.flatMap((group) =>
        group.items.map((permission) => permission.name),
    ),
);

const visibleSelectedCount = computed(
    () =>
        visiblePermissionNames.value.filter((name) => selected.value.has(name))
            .length,
);
const hasScrollablePermissions = computed(
    () => visiblePermissionNames.value.length > 10,
);

function isSelected(name: string): boolean {
    return selected.value.has(name);
}

function isOpen(key: string): boolean {
    return search.value.trim() !== '' || !collapsedGroups.value.includes(key);
}

function toggleOpen(key: string): void {
    collapsedGroups.value = isOpen(key)
        ? [...collapsedGroups.value, key]
        : collapsedGroups.value.filter((group) => group !== key);
}

function toggle(names: string[], checked: boolean): void {
    const next = new Set(props.modelValue);

    for (const name of names) {
        if (checked) {
            next.add(name);
        } else {
            next.delete(name);
        }
    }

    emit('update:modelValue', Array.from(next));
}

function selectedCount(group: PermissionGroup): number {
    return group.items.filter((permission) => isSelected(permission.name))
        .length;
}

function groupCheckboxValue(group: PermissionGroup): boolean | 'indeterminate' {
    const count = selectedCount(group);

    if (count === 0) {
        return false;
    }

    return count === group.items.length ? true : 'indeterminate';
}

function selectAllVisible(): void {
    const next = new Set(props.modelValue);

    for (const name of visiblePermissionNames.value) {
        next.add(name);
    }

    emit('update:modelValue', Array.from(next));
}

function clearVisible(): void {
    const visible = new Set(visiblePermissionNames.value);

    emit(
        'update:modelValue',
        props.modelValue.filter((name) => !visible.has(name)),
    );
}

function expandAll(): void {
    const visible = new Set(filteredGroups.value.map((group) => group.key));

    collapsedGroups.value = collapsedGroups.value.filter(
        (group) => !visible.has(group),
    );
}

function collapseAll(): void {
    const collapsed = new Set(collapsedGroups.value);

    for (const group of filteredGroups.value) {
        collapsed.add(group.key);
    }

    collapsedGroups.value = Array.from(collapsed);
}
</script>

<template>
    <div class="space-y-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Search permissions..."
                    aria-label="Search permissions"
                    :disabled="disabled"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="disabled || visiblePermissionNames.length === 0"
                    @click="selectAllVisible"
                >
                    Select visible
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="disabled || visibleSelectedCount === 0"
                    @click="clearVisible"
                >
                    Clear visible
                </Button>
                <template v-if="search.trim() === ''">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="disabled || filteredGroups.length === 0"
                        @click="expandAll"
                    >
                        Expand all
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="disabled || filteredGroups.length === 0"
                        @click="collapseAll"
                    >
                        Collapse all
                    </Button>
                </template>
            </div>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
        >
            <p>
                {{ selected.size }} of {{ permissions.length }} permissions
                selected
            </p>
            <p aria-live="polite">
                Showing {{ visiblePermissionNames.length }} of
                {{ permissions.length }} permissions
            </p>
        </div>

        <div
            v-if="filteredGroups.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{
                permissions.length === 0
                    ? 'No permissions available.'
                    : 'No permissions match your search.'
            }}
        </div>

        <ScrollArea
            v-else
            class="rounded-lg"
            :class="hasScrollablePermissions ? 'h-48' : 'h-auto'"
        >
            <div class="space-y-3 pr-3">
                <div
                    v-for="group in filteredGroups"
                    :key="group.key"
                    class="overflow-hidden rounded-lg border"
                >
                    <div
                        class="flex items-center gap-3 border-b bg-muted/40 px-4 py-3"
                    >
                        <Checkbox
                            :model-value="groupCheckboxValue(group)"
                            :disabled="disabled"
                            @click.stop
                            @update:model-value="
                                (value) =>
                                    toggle(
                                        group.items.map((item) => item.name),
                                        value === true,
                                    )
                            "
                        />

                        <button
                            type="button"
                            class="flex flex-1 items-center justify-between gap-2 text-left"
                            :aria-expanded="isOpen(group.key)"
                            @click="toggleOpen(group.key)"
                        >
                            <span class="text-sm font-medium">{{
                                group.label
                            }}</span>
                            <span
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                {{ selectedCount(group) }}/{{
                                    group.items.length
                                }}
                                <ChevronDown
                                    class="size-4 transition-transform"
                                    :class="{
                                        '-rotate-90': !isOpen(group.key),
                                    }"
                                />
                            </span>
                        </button>
                    </div>

                    <div
                        v-show="isOpen(group.key)"
                        class="grid gap-1 p-2 sm:grid-cols-2"
                    >
                        <div
                            v-for="item in group.items"
                            :key="item.name"
                            class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 hover:bg-muted/50"
                            @click="toggle([item.name], !isSelected(item.name))"
                        >
                            <Checkbox
                                :model-value="isSelected(item.name)"
                                :disabled="disabled"
                                @click.stop
                                @update:model-value="
                                    (value) =>
                                        toggle([item.name], value === true)
                                "
                            />
                            <span class="text-sm">{{ item.label }}</span>
                            <span
                                class="ml-auto font-mono text-xs text-muted-foreground"
                            >
                                {{ item.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <template #scrollbar>
                <ScrollBar orientation="vertical" />
            </template>
        </ScrollArea>
    </div>
</template>
