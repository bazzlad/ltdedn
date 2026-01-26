<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

const LOGO_SVG = '/images/logo-sm.svg';
</script>

<template>
    <Head title="Artists – LTD/EDN" />

    <div class="main-bg relative flex min-h-screen flex-col bg-black text-neutral-200 antialiased">
        <!-- background layers -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
            <div
                class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"
            ></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
        </div>

        <!-- Top nav -->
        <header class="relative z-10">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
                <Link :href="home()" class="flex items-center gap-3">
                    <img :src="LOGO_SVG" alt="LTD/EDN" class="h-16 w-auto" />
                    <span class="sr-only">LTD/EDN</span>
                </Link>
                <Link
                    :href="home()"
                    class="inline-flex items-center rounded-xl px-4 py-2 font-semibold ring-1 ring-white/20 hover:ring-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                    Back to Home
                </Link>
            </nav>
        </header>

        <!-- Main content fills the space between header and footer -->
        <main
            class="relative isolate z-10 mx-auto flex w-full max-w-7xl flex-1 flex-col justify-center px-6 pt-10 pb-16 lg:px-8 lg:pt-14"
        >
            <div class="text-center">
                <h1 class="text-4xl font-bold tracking-tight text-white sm:text-6xl">
                    Artists
                </h1>
                <p class="mt-6 text-lg leading-8 text-neutral-300">
                    Discover amazing artists and their unique collections
                </p>
            </div>

            <div class="mx-auto mt-16 max-w-5xl">
                <div class="prose prose-neutral prose-lg max-w-none prose-invert">
                    <h2 class="text-white">Featured Artists</h2>
                    <p class="text-neutral-300">
                        Welcome to our artists showcase. Here you'll find a curated selection of talented artists 
                        who create unique limited edition pieces exclusively for our platform.
                    </p>

                    <div class="not-prose mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-2xl bg-white/5 p-6 backdrop-blur-sm ring-1 ring-white/10">
                            <div class="mb-4 h-48 rounded-xl bg-white/10"></div>
                            <h3 class="text-lg font-semibold text-white">Artist One</h3>
                            <p class="mt-2 text-sm text-neutral-300">
                                Specializes in digital art and contemporary pieces.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/5 p-6 backdrop-blur-sm ring-1 ring-white/10">
                            <div class="mb-4 h-48 rounded-xl bg-white/10"></div>
                            <h3 class="text-lg font-semibold text-white">Artist Two</h3>
                            <p class="mt-2 text-sm text-neutral-300">
                                Creates stunning sculptures and mixed media works.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/5 p-6 backdrop-blur-sm ring-1 ring-white/10">
                            <div class="mb-4 h-48 rounded-xl bg-white/10"></div>
                            <h3 class="text-lg font-semibold text-white">Artist Three</h3>
                            <p class="mt-2 text-sm text-neutral-300">
                                Focuses on traditional paintings with modern interpretations.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer stays at bottom because parent is flex-col and main is flex-1 -->
        <footer class="relative z-10 border-t border-white/10 bg-black/50">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-white sm:flex-row lg:px-8">
                <p>© {{ new Date().getFullYear() }} LTD/EDN. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <Link :href="home()" class="hover:text-neutral-200">Home</Link>
                </div>
            </div>
        </footer>
    </div>
</template>