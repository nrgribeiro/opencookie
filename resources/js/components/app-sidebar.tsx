import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, Globe, LayoutGrid, ShieldCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as domainsIndex } from '@/routes/domains';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Domains',
        href: domainsIndex(),
        icon: Globe,
    },
];

const adminNavItem: NavItem = {
    title: 'Admin',
    href: adminDashboard(),
    icon: ShieldCheck,
};

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/nrgribeiro/opencookie',
        icon: FolderGit2,
    },
    {
        title: 'Setup & Compliance Guide',
        href: '/guide',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const navItems = auth.isSuperAdmin
        ? [...mainNavItems, adminNavItem]
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
