<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';

const HERO_IMG = '/images/landing-hero.jpg';
const LOGO_SVG = '/images/logo-sm.svg';

const page = usePage();
const user = page.props.auth?.user;
</script>

<style scoped>

</style>

<template>
<Head title="LTD/EDN – Limited Editions">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</Head>

<!-- KEY CHANGES: add flex flex-col; keep min-h-screen on wrapper only -->
<div class="relative min-h-screen bg-black text-neutral-200 antialiased flex flex-col main-bg">

    <!-- background layers -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
        <div class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
    </div>

    <!-- Top nav -->
    <header class="relative z-10">
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
        <div class="flex items-center gap-3">
        <img :src="LOGO_SVG" alt="LTD/EDN" class="h-16 w-auto" />
        <span class="sr-only">LTD/EDN</span>
        </div>
        <div class="flex items-center gap-3 text-sm">
        <Link
            v-if="user"
            :href="dashboard()"
            class="inline-flex items-center rounded-xl px-4 py-2 font-semibold ring-1 ring-white/20 hover:ring-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
        >
            Dashboard
        </Link>
        <template v-else>
            <Link
            :href="login()"
            class="inline-flex items-center rounded-xl bg-white/10 px-4 py-2 font-semibold ring-1 ring-white/15 hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
            >
            Log in
            </Link>
            <Link
            :href="register()"
            class="inline-flex items-center rounded-xl px-4 py-2 font-semibold ring-1 ring-white/20 hover:ring-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
            >
            Create account
            </Link>
        </template>
        </div>
    </nav>
    </header>

    <!-- Hero fills the space between header and footer -->
    <main
    class="relative z-10 isolate mx-auto flex w-full max-w-7xl flex-1 flex-col items-center justify-center px-6 pb-16 pt-10 text-center lg:px-8 lg:pt-14"
    >
    <div class="mx-auto max-w-2xl">
        <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">
        Digitally authenticated limited editions
        </h1>
        <p class="mt-4 text-base leading-relaxed text-neutral-300">
        A new way to collect and verify art. Built with artist-first values, exceptional quality, and a modern ownership record that travels with the work.
        </p>
        <div class="mt-8 flex items-center justify-center gap-3">
        <Link
            :href="register()"
            class="inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-black shadow-sm hover:bg-neutral-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-black/50"
        >
            Create account
        </Link>
        <Link
            :href="login()"
            class="inline-flex items-center rounded-xl bg-white/10 px-5 py-3 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
        >
            Log in
        </Link>
        </div>
    </div>
    </main>

    <!-- Footer stays at bottom because parent is flex-col and main is flex-1 -->
    <footer class="relative z-10 border-t border-white/10 bg-black/50">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-neutral-400 sm:flex-row lg:px-8">
        <p>© {{ new Date().getFullYear() }} LTD/EDN. All rights reserved.</p>
        <div class="flex items-center gap-4">
        <Link href="#" class="hover:text-neutral-200">Privacy</Link>
        <span aria-hidden="true" class="text-white/20">•</span>
        <Link href="#" class="hover:text-neutral-200">Terms</Link>
        </div>
    </div>
    </footer>
</div>
</template>
