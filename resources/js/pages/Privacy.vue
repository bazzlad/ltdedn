<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

const LOGO_SVG = '/images/logo-sm.svg';
</script>

<template>
    <Head title="Privacy Policy – LTD/EDN" />

    <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
        <header class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
                <Link :href="home()" class="flex items-center gap-3">
                    <img :src="LOGO_SVG" alt="LTD/EDN" class="h-12 w-auto" />
                    <span class="sr-only">LTD/EDN</span>
                </Link>
                <Link
                    :href="home()"
                    class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-neutral-700 ring-1 ring-neutral-300 hover:bg-neutral-50 dark:text-neutral-300 dark:ring-neutral-700 dark:hover:bg-neutral-800"
                >
                    Back to Home
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-12 lg:px-8 lg:py-16">
            <div class="prose prose-neutral max-w-none dark:prose-invert">
                <h1>Privacy Policy</h1>
                <p class="text-lg text-neutral-600 dark:text-neutral-400">Last updated: {{ new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>

                <p>
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                </p>
            </div>
        </main>

        <footer class="border-t border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <div class="mx-auto max-w-7xl px-6 py-6 text-center text-sm text-neutral-600 dark:text-neutral-400">
                <p>© {{ new Date().getFullYear() }} LTD/EDN. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>

