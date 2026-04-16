<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type ShopProduct = {
    id: number;
    name: string;
    image: string | null;
    shop_url: string;
};

defineProps<{
    products: ShopProduct[];
}>();
</script>

<template>
    <Head title="Shop – LTD/EDN" />

    <ShopLayout>
        <header class="mb-10 max-w-3xl">
            <h1 class="font-sans text-3xl font-semibold tracking-tight text-white sm:text-4xl">SHOP</h1>
            <p class="mt-3 font-mono text-sm leading-relaxed tracking-wide text-white/75">
                Limited-edition releases from the LTD/EDN roster. Each item is numbered, authenticated and shipped with a QR-linked certificate of
                provenance.
            </p>
        </header>

        <div v-if="products.length === 0" class="border border-white/10 bg-neutral-900/50 p-8 text-center ring-1 ring-white/5">
            <p class="font-mono text-sm tracking-wide text-white/70">No items are available right now. Please check back soon.</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="product in products"
                :key="product.id"
                :href="product.shop_url"
                class="group block focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
            >
                <div class="relative flex aspect-square w-full items-center justify-center overflow-hidden border-4 border-white/80 bg-neutral-900">
                    <img
                        v-if="product.image"
                        :src="product.image"
                        :alt="product.name"
                        class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                    <svg v-else class="h-12 w-12 text-neutral-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                    </svg>
                </div>
                <div class="mt-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[0.625rem] font-bold tracking-widest text-neutral-500">EDITION</p>
                        <h2 class="mt-1 truncate text-sm font-bold tracking-wider text-white">
                            {{ product.name.toUpperCase() }}
                        </h2>
                    </div>
                    <span class="shrink-0 text-[0.625rem] font-bold tracking-widest text-white/60 transition-colors group-hover:text-white">
                        VIEW →
                    </span>
                </div>
            </Link>
        </div>
    </ShopLayout>
</template>
