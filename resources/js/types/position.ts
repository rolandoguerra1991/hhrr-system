export type Position = {
    id: number;
    name: string;
    department_id: number | null;
    department_name: string | null;
    assignments_count: number;
    is_in_use: boolean;
    can: {
        update: boolean;
        delete: boolean;
    };
};

export type PositionDetails = {
    id: number;
    name: string;
    department_id: number | null;
    is_in_use: boolean;
};

export type EmployeePositionOption = {
    id: number;
    name: string;
    department_id: number | null;
    department_name: string | null;
};
