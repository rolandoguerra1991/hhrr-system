<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PermissionChecklist from '@/components/PermissionChecklist.vue';
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
import type { PermissionOption } from '@/types';

defineProps<{
    permissions: PermissionOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Roles',
                href: RoleController.index.url(),
            },
            {
                title: 'New role',
                href: RoleController.create.url(),
            },
        ],
    },
});

const form = useForm({
    name: '',
    permissions: [] as string[],
});

function submit(): void {
    form.post(RoleController.store.url());
}
</script>

<template>
    <Head title="New role" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link :href="RoleController.index.url()">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <Heading
                title="New role"
                description="Choose a name and the permissions this role grants."
            />
        </div>

        <form
            class="mx-auto w-full max-w-4xl space-y-6"
            @submit.prevent="submit"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Role details</CardTitle>
                    <CardDescription>
                        A descriptive name makes the role easy to recognise.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        name="name"
                        placeholder="Editor"
                        autocomplete="off"
                        data-test="role-name-input"
                    />
                    <InputError :message="form.errors.name" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Permissions</CardTitle>
                    <CardDescription>
                        Select the permissions this role should grant.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <PermissionChecklist
                        v-model="form.permissions"
                        :permissions="permissions"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.permissions"
                    />
                </CardContent>
            </Card>

            <div class="flex items-center justify-end gap-3">
                <Button variant="secondary" as-child>
                    <Link :href="RoleController.index.url()">Cancel</Link>
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="create-role-button"
                >
                    Create role
                </Button>
            </div>
        </form>
    </div>
</template>
