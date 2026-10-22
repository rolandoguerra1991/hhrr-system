export type UserRoleOption = {
    name: string;
    is_protected: boolean;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    department: string | null;
    is_protected: boolean;
    is_self: boolean;
    can: {
        update: boolean;
        delete: boolean;
    };
};

export type UserAbility = {
    assignSuperAdmin: boolean;
};
