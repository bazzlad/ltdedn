<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

const LOGO_SVG = '/images/logo-sm.svg';
</script>

<template>
    <Head title="Invest – LTD/EDN" />

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
                    Investment Opportunities
                </h1>
                <p class="mt-6 text-lg leading-8 text-neutral-300">
                    Explore exclusive investment opportunities in limited edition art and collectibles
                </p>
            </div>

            <div class="mx-auto mt-16 max-w-5xl">
                <div class="prose prose-neutral prose-lg max-w-none prose-invert">
                    <h2 class="text-white">Why Invest with LTD/EDN?</h2>
                    <p class="text-neutral-300">
                        Our platform offers unique investment opportunities in limited edition art pieces and collectibles. 
                        Each piece is carefully curated and authenticated, providing both artistic value and investment potential.
                    </p>

                    <div class="not-prose mt-12 grid gap-8 lg:grid-cols-3">
                        <div class="rounded-2xl bg-white/5 p-8 backdrop-blur-sm ring-1 ring-white/10">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                                </svg>
                            </div>
                            <h3 class="mt-6 text-lg font-semibold text-white">Growth Potential</h3>
                            <p class="mt-2 text-neutral-300">
                                Limited edition pieces often appreciate in value over time, especially from emerging artists.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/5 p-8 backdrop-blur-sm ring-1 ring-white/10">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                </svg>
                            </div>
                            <h3 class="mt-6 text-lg font-semibold text-white">Authentication</h3>
                            <p class="mt-2 text-neutral-300">
                                Every piece comes with verified authenticity and provenance documentation.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/5 p-8 backdrop-blur-sm ring-1 ring-white/10">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                            </div>
                            <h3 class="mt-6 text-lg font-semibold text-white">Exclusive Access</h3>
                            <p class="mt-2 text-neutral-300">
                                Get early access to new releases and exclusive collections from top artists.
                            </p>
                        </div>
                    </div>

                    <div class="mt-16 rounded-2xl bg-white/5 p-8 backdrop-blur-sm ring-1 ring-white/10">
                        <h2 class="text-2xl font-bold text-white">Ready to Start Investing?</h2>
                        <p class="mt-4 text-neutral-300">
                            Contact our investment team to learn more about current opportunities and get started with your art investment portfolio.
                        </p>
                        <div class="mt-6">
                            <a
                                href="mailto:invest@ltdedn.com"
                                class="inline-flex items-center rounded-xl bg-white/10 px-6 py-3 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            >
                                Get Started
                            </a>
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