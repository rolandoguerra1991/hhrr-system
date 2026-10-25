<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, UserRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeStatusBadge from '@/components/employees/EmployeeStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { EmployeeListItem, Paginated } from '@/types';

const props = defineProps<{
    employees: Paginated<EmployeeListItem>;
    filters: {
        search: string;
        status: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Employees',
                href: EmployeeController.index.url(),
            },
        ],
    },
});

const page = usePage();
const canCreateEmployee = computed(() => page.props.auth.can.employees.create);
const search = ref(props.filters.search);
const status = ref(props.filters.status || 'all');

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

function visitDirectory(): void {
    router.get(
        EmployeeController.index.url({
            query: {
                search: search.value.trim() || undefined,
                status: status.value === 'all' ? undefined : status.value,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(visitDirectory, 300);
});

watch(status, visitDirectory);
</script>

<template>
    <Head title="Employees" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Employees"
                description="Manage employee records, employment status, and department assignments."
            />

            <Button v-if="canCreateEmployee" as-child>
                <Link :href="EmployeeController.create.url()">
                    <Plus class="size-4" />
                    New employee
                </Link>
            </Button>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Search employees..."
                    aria-label="Search employees"
                    data-test="search-employees"
                />
            </div>

            <Select v-model="status">
                <SelectTrigger
                    class="w-full sm:w-48"
                    aria-label="Filter employees by status"
                    data-test="filter-employee-status"
                >
                    <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="terminated">Terminated</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Employee</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="hidden md:table-cell">
                            Department
                        </TableHead>
                        <TableHead class="hidden lg:table-cell">
                            Position
                        </TableHead>
                        <TableHead class="hidden lg:table-cell">
                            Reporting manager
                        </TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="employee in employees.data"
                        :key="employee.id"
                    >
                        <TableCell>
                            <div class="flex items-center gap-2 font-medium">
                                <UserRound
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                <span>{{ employee.full_name }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ employee.employee_number }}
                                <span v-if="employee.work_email">
                                    · {{ employee.work_email }}
                                </span>
                            </p>
                            <p
                                class="mt-1 text-xs text-muted-foreground md:hidden"
                            >
                                {{
                                    employee.current_department ??
                                    'No department'
                                }}
                                <span v-if="employee.current_position">
                                    · {{ employee.current_position }}
                                </span>
                            </p>
                        </TableCell>
                        <TableCell>
                            <EmployeeStatusBadge :status="employee.status" />
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            {{ employee.current_department ?? '—' }}
                        </TableCell>
                        <TableCell class="hidden lg:table-cell">
                            {{ employee.current_position ?? '—' }}
                        </TableCell>
                        <TableCell class="hidden lg:table-cell">
                            {{ employee.reporting_manager ?? '—' }}
                        </TableCell>
                        <TableCell class="text-right">
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="
                                        EmployeeController.show.url(employee.id)
                                    "
                                >
                                    View
                                </Link>
                            </Button>
                            <Button
                                v-if="employee.can.update"
                                class="ml-2"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        EmployeeController.edit.url(employee.id)
                                    "
                                >
                                    Edit
                                </Link>
                            </Button>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="employees.data.length === 0">
                        <TableCell
                            colspan="6"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No employees match these filters.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination
            :links="employees.links"
            :from="employees.from"
            :to="employees.to"
            :total="employees.total"
        />
    </div>
</template>
