<script setup lang="ts">
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Users, Palette, ArrowLeft, Package, BookOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '../AppLogo.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');
const isArtist = computed(() => user.value?.role === 'artist');

// Admin-only navigation items
const adminNavItems: NavItem[] = [
    {
        title: 'Users',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Artists',
        href: '/admin/artists',
        icon: Palette,
    },
];

// Shared navigation items (both admin and artist)
const sharedNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin',
        icon: LayoutGrid,
    },
    {
        title: 'Products',
        href: '/admin/products',
        icon: Package,
    },
    {
        title: 'Editions',
        href: '/admin/editions',
        icon: BookOpen,
    },
];

// Compute navigation items based on user role
const mainNavItems = computed(() => {
    const items = [...sharedNavItems];

    // Add admin-only items if user is admin
    if (isAdmin.value) {
        items.splice(1, 0, ...adminNavItems); // Insert after Dashboard
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Back to App',
        href: '/dashboard',
        icon: ArrowLeft,
    },
];

// Compute panel title based on user role
const panelTitle = computed(() => {
    if (isAdmin.value) return 'Admin Panel';
    if (isArtist.value) return 'Artist Panel';
    return 'Management Panel';
});

const panelSubtitle = computed(() => {
    if (isAdmin.value) return 'System Management';
    if (isArtist.value) return 'Content Management';
    return 'Management Console';
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/admin">
                            <AppLogo />
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ panelTitle }}</span>
                                <span class="truncate text-xs">{{ panelSubtitle }}</span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                    <SidebarMenuButton as-child>
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in footerNavItems" :key="item.title">
                    <SidebarMenuButton as-child>
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
</template>
