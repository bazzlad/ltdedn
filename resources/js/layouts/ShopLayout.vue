<script setup lang="ts">
import CartBadge from '@/components/Shop/CartBadge.vue';
import { Link } from '@inertiajs/vue3';

const LOGO_SVG = '/images/logo-sm.svg';

withDefaults(
    defineProps<{
        showCart?: boolean;
    }>(),
    { showCart: true },
);
</script>

<template>
    <div class="main-bg relative flex min-h-screen flex-col bg-black text-neutral-200 antialiased">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
            <div
                class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"
            ></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
        </div>

        <header class="relative z-10">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
                <Link href="/shop" class="flex items-center gap-3">
                    <img :src="LOGO_SVG" alt="LTD/EDN" class="h-10 w-auto" />
                    <span class="sr-only">LTD/EDN Shop</span>
                </Link>
                <div v-if="showCart" class="flex items-center gap-4">
                    <CartBadge />
                </div>
            </nav>
        </header>

        <main class="relative z-10 mx-auto w-full max-w-7xl flex-1 px-6 pt-4 pb-12 lg:px-8 lg:pb-20">
            <slot />
        </main>

        <footer class="relative z-10">
            <div
                class="mx-auto flex max-w-7xl items-center justify-center px-6 py-8 text-xs font-semibold tracking-wide text-white/80 lg:justify-end lg:px-8"
            >
                <p>&copy; {{ new Date().getFullYear() }} LTD/EDN</p>
            </div>
        </footer>
    </div>
</template>
