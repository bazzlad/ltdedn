<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import { dashboard, logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <AppShell variant="header">
        <!-- Simple Header -->
        <header class="border-b border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <Link :href="dashboard().url" class="flex items-center space-x-2">
                            <img src="/images/logo-sm.svg" alt="Logo" class="h-8 w-8" />
                            <span class="text-xl font-bold text-slate-900 dark:text-slate-100">Collection</span>
                        </Link>
                    </div>

                    <!-- User Menu -->
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-slate-600 dark:text-slate-400">Welcome, {{ user?.name }}</div>

                        <!-- Admin/Artist Panel Link -->
                        <Link
                            v-if="user?.role === 'admin' || user?.role === 'artist'"
                            :href="adminDashboard().url"
                            class="inline-flex items-center space-x-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                            <span>{{ user?.role === 'admin' ? 'Admin Panel' : 'Artist Panel' }}</span>
                        </Link>

                        <!-- <Link
                            href="/settings"
                            class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            Settings
                        </Link> -->

                        <Link
                            :href="logout()"
                            @click="handleLogout"
                            as="button"
                            class="inline-flex items-center space-x-1 text-sm text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                />
                            </svg>
                            <span>Logout</span>
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <AppContent variant="header" class="px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </AppContent>
    </AppShell>
</template>
