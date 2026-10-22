export type Department = {
    id: number;
    name: string;
    parent_id: number | null;
    depth: number;
    children_count: number;
    users_count: number;
    positions_count: number;
    manager_name: string | null;
    can: {
        update: boolean;
        delete: boolean;
    };
};

export type DepartmentOption = {
    id: number;
    name: string;
    depth: number;
};

export type DepartmentDetails = {
    id: number;
    name: string;
    parent_id: number | null;
    manager_id: number | null;
};

export type DepartmentManagerOption = {
    id: number;
    name: string;
};
