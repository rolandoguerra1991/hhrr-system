export type EmployeeStatus = 'active' | 'terminated';

export type EmployeeListItem = {
    id: number;
    employee_number: string;
    full_name: string;
    work_email: string | null;
    status: EmployeeStatus;
    current_department: string | null;
    current_position: string | null;
    reporting_manager: string | null;
    can: {
        update: boolean;
    };
};

export type EmployeeUserOption = {
    id: number;
    name: string;
    email: string;
};

export type EmployeeDetails = {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    work_email: string | null;
    status: EmployeeStatus;
    user: EmployeeUserOption | null;
    current_period: {
        started_on: string;
        start_reason: string;
    } | null;
    current_assignment: {
        department_id: number;
        department_name: string;
        position_id: number | null;
        position_name: string | null;
        effective_from: string;
    } | null;
    reporting_manager: string | null;
    departments_managed: string[];
    can: {
        update: boolean;
    };
};

export type EmploymentPeriodHistory = {
    id: number;
    started_on: string;
    ended_on: string | null;
    start_reason: string;
    termination_reason: string | null;
    created_by: string | null;
    terminated_by: string | null;
};

export type EmployeeAssignmentHistory = {
    id: number;
    department_name: string;
    position_name: string | null;
    effective_from: string;
    effective_to: string | null;
    change_reason: string;
    changed_by: string | null;
};

export type EmployeeChangeLog = {
    id: number;
    reason: string;
    changes: Record<
        string,
        { old: string | number | null; new: string | number | null }
    >;
    changed_by: string | null;
    created_at: string;
};

export type EmployeeFormDetails = {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    work_email: string | null;
    user_id: number | null;
};
