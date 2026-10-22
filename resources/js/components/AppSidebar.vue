<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    Building2,
    KeyRound,
    LayoutGrid,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import DepartmentController from '@/actions/App/Http/Controllers/DepartmentController';
import PermissionController from '@/actions/App/Http/Controllers/PermissionController';
import PositionController from '@/actions/App/Http/Controllers/PositionController';
import UserController from '@/actions/App/Http/Controllers/UserController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import SidebarNavMenu from '@/components/SidebarNavMenu.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const administrationNavItems = computed<NavItem[]>(() => {
    const can = page.props.auth.can;
    const items: NavItem[] = [];

    if (can.users.view) {
        items.push({
            title: 'Users',
            href: UserController.index.url(),
            icon: UserRound,
        });
    }

    if (can.departments.view) {
        items.push({
            title: 'Departments',
            href: DepartmentController.index.url(),
            icon: Building2,
        });
    }

    if (can.positions.view) {
        items.push({
            title: 'Positions',
            href: PositionController.index.url(),
            icon: BriefcaseBusiness,
        });
    }

    if (can.roles.view) {
        items.push({
            title: 'Roles',
            href: RoleController.index.url(),
            icon: ShieldCheck,
        });
    }

    if (can.permissions.view) {
        items.push({
            title: 'Permissions',
            href: PermissionController.index.url(),
            icon: KeyRound,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarNavMenu label="Main Menu" :items="mainNavItems" />

            <SidebarNavMenu
                v-if="administrationNavItems.length > 0"
                label="Administration"
                :items="administrationNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
