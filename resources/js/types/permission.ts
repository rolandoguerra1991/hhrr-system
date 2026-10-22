export type PermissionOption = {
    name: string;
    label: string;
    group: string;
    group_label: string;
};

export type PermissionCatalogueItem = PermissionOption & {
    id: number;
    roles_count: number;
    can: {
        delete: boolean;
    };
};

export type Role = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
    is_protected: boolean;
    can: {
        update: boolean;
        delete: boolean;
    };
};

export type RoleUser = {
    id: number;
    name: string;
    email: string;
};
