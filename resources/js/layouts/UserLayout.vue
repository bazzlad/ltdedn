<script setup lang="ts">
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
        <div class="main-bg relative flex min-h-screen flex-col bg-black text-neutral-200 antialiased">
            <!-- Background layers -->
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
                <div
                    class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"
                ></div>
                <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
            </div>

            <!-- Header -->
            <header class="relative z-10 border-b border-white/10 bg-black/50 backdrop-blur-sm">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 items-center justify-between">
                        <!-- Logo -->
                        <div class="flex items-center">
                            <Link :href="dashboard().url" class="flex items-center gap-2">
                                <img src="/images/logo-sm.svg" alt="Logo" class="h-8 w-8" />
                                <span class="text-xl font-bold text-white">Collection</span>
                            </Link>
                        </div>

                        <!-- User Menu -->
                        <div class="flex items-center gap-4">
                            <div class="text-sm font-medium text-neutral-400">Welcome, {{ user?.name }}</div>

                            <!-- Admin/Artist Panel Link -->
                            <Link
                                v-if="user?.role === 'admin' || user?.role === 'artist'"
                                :href="adminDashboard().url"
                                class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-black shadow-sm transition-all hover:bg-neutral-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
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

                            <Link
                                :href="logout()"
                                @click="handleLogout"
                                as="button"
                                class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-neutral-300 ring-1 ring-white/20 transition-all hover:bg-white/10 hover:text-white hover:ring-white/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
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
            <main class="relative z-10 flex-1">
                <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                    <slot />
                </div>
            </main>
        </div>
    </AppShell>
</template>
