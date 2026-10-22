<script setup lang="ts">
import { Search, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import type { UserRoleOption } from '@/types';

const props = defineProps<{
    roles: UserRoleOption[];
    modelValue: string[];
    canAssignSuperAdmin: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const search = ref('');
const selected = computed(() => new Set(props.modelValue));
const filteredRoles = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return props.roles;
    }

    return props.roles.filter((role) =>
        role.name.toLowerCase().includes(query),
    );
});

function isSelected(name: string): boolean {
    return selected.value.has(name);
}

function isLocked(role: UserRoleOption): boolean {
    return role.is_protected && !props.canAssignSuperAdmin;
}

function toggle(name: string, checked: boolean): void {
    const next = new Set(props.modelValue);

    if (checked) {
        next.add(name);
    } else {
        next.delete(name);
    }

    emit('update:modelValue', Array.from(next));
}

function toggleFromRow(role: UserRoleOption): void {
    if (isLocked(role)) {
        return;
    }

    toggle(role.name, !isSelected(role.name));
}
</script>

<template>
    <div class="grid gap-3">
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="search"
                class="pl-9"
                placeholder="Search roles..."
                aria-label="Search roles"
                data-test="role-search-input"
            />
        </div>

        <ScrollArea class="h-64 rounded-md border">
            <div
                v-if="filteredRoles.length === 0"
                class="flex h-64 items-center justify-center px-4 text-center text-sm text-muted-foreground"
            >
                {{
                    props.roles.length === 0
                        ? 'No roles available.'
                        : 'No roles match your search.'
                }}
            </div>
            <div v-else class="grid gap-1 p-2">
                <div
                    v-for="role in filteredRoles"
                    :key="role.name"
                    class="flex items-center gap-3 rounded-md border px-3 py-2.5"
                    :class="{
                        'cursor-pointer hover:bg-muted/50': !isLocked(role),
                        'opacity-70': isLocked(role),
                    }"
                    @click="toggleFromRow(role)"
                >
                    <Checkbox
                        :model-value="isSelected(role.name)"
                        :disabled="isLocked(role)"
                        :data-test="`role-checkbox-${role.name}`"
                        @click.stop
                        @update:model-value="
                            (value) => toggle(role.name, value === true)
                        "
                    />

                    <span class="flex items-center gap-2 text-sm font-medium">
                        <ShieldCheck
                            v-if="role.is_protected"
                            class="size-4 text-muted-foreground"
                        />
                        {{ role.name }}
                    </span>

                    <span
                        v-if="isLocked(role)"
                        class="ml-auto text-xs text-muted-foreground"
                        title="Only a Super Admin can change who holds this role"
                    >
                        Super Admin only
                    </span>
                </div>
            </div>
            <template #scrollbar>
                <ScrollBar orientation="vertical" />
            </template>
        </ScrollArea>
    </div>
</template>
