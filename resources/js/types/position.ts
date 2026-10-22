export type Position = {
    id: number;
    name: string;
    department_id: number | null;
    department_name: string | null;
    can: {
        update: boolean;
        delete: boolean;
    };
};

export type PositionDetails = {
    id: number;
    name: string;
    department_id: number | null;
};
