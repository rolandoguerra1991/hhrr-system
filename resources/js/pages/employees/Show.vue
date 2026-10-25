<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BriefcaseBusiness, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import AssignEmployeeDialog from '@/components/employees/AssignEmployeeDialog.vue';
import EmployeeStatusBadge from '@/components/employees/EmployeeStatusBadge.vue';
import RehireEmployeeDialog from '@/components/employees/RehireEmployeeDialog.vue';
import TerminateEmployeeDialog from '@/components/employees/TerminateEmployeeDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    EmployeeAssignmentHistory,
    EmployeeChangeLog,
    EmployeeDetails,
    EmployeeDepartmentOption,
    EmployeePositionOption,
    EmploymentPeriodHistory,
} from '@/types';

const props = defineProps<{
    employee: EmployeeDetails;
    periods: EmploymentPeriodHistory[];
    assignments: EmployeeAssignmentHistory[];
    changeLogs: EmployeeChangeLog[];
    departmentOptions: EmployeeDepartmentOption[];
    positionOptions: EmployeePositionOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Employees',
                href: EmployeeController.index.url(),
            },
            {
                title: 'Employee details',
                href: EmployeeController.index.url(),
            },
        ],
    },
});

const assignmentDialogOpen = ref(false);
const terminationDialogOpen = ref(false);
const rehireDialogOpen = ref(false);

function changeValue(value: string | number | null): string {
    return value === null ? '—' : String(value);
}

function fieldLabel(field: string): string {
    return field.replaceAll('_', ' ');
}
</script>

<template>
    <Head :title="employee.full_name" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon-sm" as-child>
                    <Link :href="EmployeeController.index.url()">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <Heading
                    :title="employee.full_name"
                    :description="`Employee ${employee.employee_number}`"
                />
                <EmployeeStatusBadge :status="employee.status" />
            </div>

            <div v-if="employee.can.update" class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="EmployeeController.edit.url(employee.id)">
                        <Pencil class="size-4" />
                        Edit
                    </Link>
                </Button>
                <Button
                    v-if="employee.status === 'active'"
                    variant="outline"
                    data-test="assign-employee-button"
                    @click="assignmentDialogOpen = true"
                >
                    <Plus class="size-4" />
                    Assign
                </Button>
                <Button
                    v-if="employee.status === 'active'"
                    variant="destructive"
                    data-test="terminate-employee-button"
                    @click="terminationDialogOpen = true"
                >
                    Terminate
                </Button>
                <Button
                    v-if="employee.status === 'terminated'"
                    data-test="rehire-employee-button"
                    @click="rehireDialogOpen = true"
                >
                    Rehire
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Employee information</CardTitle>
                    <CardDescription>
                        Identity and linked login account.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Employee number
                        </p>
                        <p class="font-medium">
                            {{ employee.employee_number }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Work email</p>
                        <p class="font-medium">
                            {{ employee.work_email ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Login account
                        </p>
                        <p v-if="employee.user" class="font-medium">
                            {{ employee.user.name }}
                        </p>
                        <p v-else class="font-medium">No linked account</p>
                        <p
                            v-if="employee.user"
                            class="text-sm text-muted-foreground"
                        >
                            {{ employee.user.email }}
                        </p>
                    </div>
                    <div v-if="employee.departments_managed.length > 0">
                        <p class="text-sm text-muted-foreground">
                            Departments managed
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <Badge
                                v-for="department in employee.departments_managed"
                                :key="department"
                                variant="outline"
                            >
                                {{ department }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Current employment</CardTitle>
                    <CardDescription>
                        Current period, assignment, and reporting manager.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Employment started
                        </p>
                        <p class="font-medium">
                            {{ employee.current_period?.started_on ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Start reason
                        </p>
                        <p class="font-medium">
                            {{ employee.current_period?.start_reason ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Department</p>
                        <p class="font-medium">
                            {{
                                employee.current_assignment?.department_name ??
                                'Not assigned'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Position</p>
                        <p class="font-medium">
                            {{
                                employee.current_assignment?.position_name ??
                                '—'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Assignment effective
                        </p>
                        <p class="font-medium">
                            {{
                                employee.current_assignment?.effective_from ??
                                '—'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Reporting manager
                        </p>
                        <p class="font-medium">
                            {{ employee.reporting_manager ?? '—' }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Employment periods</CardTitle>
                <CardDescription>
                    Employment start and termination history.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Started</TableHead>
                                <TableHead>Ended</TableHead>
                                <TableHead>Start reason</TableHead>
                                <TableHead>Termination reason</TableHead>
                                <TableHead>Recorded by</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="period in periods"
                                :key="period.id"
                            >
                                <TableCell>{{ period.started_on }}</TableCell>
                                <TableCell>
                                    {{ period.ended_on ?? 'Current' }}
                                </TableCell>
                                <TableCell>{{ period.start_reason }}</TableCell>
                                <TableCell>
                                    {{ period.termination_reason ?? '—' }}
                                </TableCell>
                                <TableCell>
                                    {{
                                        period.terminated_by ??
                                        period.created_by ??
                                        '—'
                                    }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="periods.length === 0">
                                <TableCell
                                    colspan="5"
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No employment periods recorded.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>
                    <BriefcaseBusiness
                        class="mr-2 inline size-4 text-muted-foreground"
                    />
                    Assignment history
                </CardTitle>
                <CardDescription>
                    Department and position changes remain available after
                    transfers and termination.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Effective from</TableHead>
                                <TableHead>Effective to</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Changed by</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="assignment in assignments"
                                :key="assignment.id"
                            >
                                <TableCell>
                                    {{ assignment.effective_from }}
                                </TableCell>
                                <TableCell>
                                    {{ assignment.effective_to ?? 'Current' }}
                                </TableCell>
                                <TableCell>
                                    {{ assignment.department_name }}
                                </TableCell>
                                <TableCell>
                                    {{ assignment.position_name ?? '—' }}
                                </TableCell>
                                <TableCell>
                                    {{ assignment.change_reason }}
                                </TableCell>
                                <TableCell>
                                    {{ assignment.changed_by ?? '—' }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="assignments.length === 0">
                                <TableCell
                                    colspan="6"
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No department assignments recorded.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Change history</CardTitle>
                <CardDescription>
                    Audited edits to employee identity and account-link fields.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div
                    v-for="log in changeLogs"
                    :key="log.id"
                    class="grid gap-2 rounded-lg border p-4"
                >
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="font-medium">{{ log.reason }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ log.created_at }} ·
                            {{ log.changed_by ?? 'Unknown user' }}
                        </p>
                    </div>
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div
                            v-for="(change, field) in log.changes"
                            :key="field"
                            class="rounded-md bg-muted/40 p-3"
                        >
                            <dt class="font-medium capitalize">
                                {{ fieldLabel(field) }}
                            </dt>
                            <dd class="text-muted-foreground">
                                {{ changeValue(change.old) }}
                                <span aria-hidden="true">→</span>
                                {{ changeValue(change.new) }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <p
                    v-if="changeLogs.length === 0"
                    class="py-4 text-center text-sm text-muted-foreground"
                >
                    No core employee changes have been recorded.
                </p>
            </CardContent>
        </Card>

        <AssignEmployeeDialog
            v-if="employee.can.update && employee.status === 'active'"
            v-model="assignmentDialogOpen"
            :employee-id="employee.id"
            :employee-name="employee.full_name"
            :department-options="departmentOptions"
            :position-options="positionOptions"
        />
        <TerminateEmployeeDialog
            v-if="employee.can.update && employee.status === 'active'"
            v-model="terminationDialogOpen"
            :employee-id="employee.id"
            :employee-name="employee.full_name"
            :departments-managed="employee.departments_managed"
        />
        <RehireEmployeeDialog
            v-if="employee.can.update && employee.status === 'terminated'"
            v-model="rehireDialogOpen"
            :employee-id="employee.id"
            :employee-name="employee.full_name"
        />
    </div>
</template>
