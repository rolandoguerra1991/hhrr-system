<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/InputError.vue';
import MultiSelectCombobox from '@/components/MultiSelectCombobox.vue';
import PasswordInput from '@/components/PasswordInput.vue';
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
import type { ComboboxOption, UserAbility, UserRoleOption } from '@/types';

const props = withDefaults(
    defineProps<{
        roles: UserRoleOption[];
        can: UserAbility;
        user?: {
            id: number;
            name: string;
            email: string;
        };
        roleNames?: string[];
        stickyActions?: boolean;
    }>(),
    {
        roleNames: () => [],
        stickyActions: false,
    },
);

const isEditing = computed(() => props.user !== undefined);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    roles: [...props.roleNames],
});

const roleOptions = computed<ComboboxOption[]>(() =>
    props.roles.map((role) => ({
        value: role.name,
        label: role.name,
        hint: isLocked(role) ? 'Super Admin only' : undefined,
        disabled: isLocked(role),
    })),
);

function isLocked(role: UserRoleOption): boolean {
    return role.is_protected && !props.can.assignSuperAdmin;
}

function submit(): void {
    if (props.user) {
        form.put(UserController.update.url(props.user.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(UserController.store.url());
}
</script>

<template>
    <form class="mx-auto w-full max-w-4xl space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Account</CardTitle>
                <CardDescription>
                    {{
                        isEditing
                            ? 'Leave the password empty to keep the current one.'
                            : 'The user signs in with this email and password.'
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-5 md:grid-cols-2">
                <div class="grid gap-2">
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
                        autocomplete="name"
                        required
                        :aria-invalid="Boolean(form.errors.name)"
                        data-test="user-name-input"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">
                        Email
                        <span class="text-destructive" aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                        :aria-invalid="Boolean(form.errors.email)"
                        data-test="user-email-input"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">
                        {{ isEditing ? 'New password' : 'Password' }}
                        <span
                            v-if="!isEditing"
                            class="text-destructive"
                            aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        name="password"
                        autocomplete="new-password"
                        :required="!isEditing"
                        :aria-invalid="Boolean(form.errors.password)"
                        data-test="user-password-input"
                    />
                    <p v-if="!isEditing" class="text-sm text-muted-foreground">
                        Choose a strong password that is hard to guess.
                    </p>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        {{
                            isEditing
                                ? 'Confirm new password'
                                : 'Confirm password'
                        }}
                        <span
                            v-if="!isEditing"
                            class="text-destructive"
                            aria-hidden="true"
                            >*</span
                        >
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        :required="!isEditing"
                        :aria-invalid="
                            Boolean(form.errors.password_confirmation)
                        "
                        data-test="user-password-confirmation-input"
                    />
                    <p v-if="!isEditing" class="text-sm text-muted-foreground">
                        Re-enter the password to confirm it.
                    </p>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <div class="md:col-span-2">
                    <MultiSelectCombobox
                        id="roles"
                        v-model="form.roles"
                        test-id="role"
                        label="Roles"
                        hint="Roles decide what the user can do. An account without roles can sign in but has no administration access."
                        :options="roleOptions"
                        placeholder="Select roles..."
                        search-placeholder="Search roles..."
                        empty-text="No roles match your search."
                        no-options-text="No roles available."
                        :invalid="Boolean(form.errors.roles)"
                    >
                        <template #count="{ count }">
                            {{ count }}
                            {{ count === 1 ? 'role' : 'roles' }} selected
                        </template>

                        <InputError :message="form.errors.roles" />
                    </MultiSelectCombobox>
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
                <Link :href="UserController.index.url()">Cancel</Link>
            </Button>
            <Button
                type="submit"
                :disabled="form.processing"
                :data-test="
                    isEditing ? 'update-user-button' : 'create-user-button'
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
                          : 'Create user'
                }}
            </Button>
        </div>
    </form>
</template>
