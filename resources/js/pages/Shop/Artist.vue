<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type ArtistProfile = {
    id: number;
    name: string;
    slug: string;
    bio: string | null;
    hero_image: string | null;
};

type ArtistProduct = {
    id: number;
    name: string;
    slug: string;
    image: string | null;
    shop_url: string;
};

defineProps<{
    artist: ArtistProfile;
    products: ArtistProduct[];
}>();
</script>

<template>
    <Head :title="`${artist.name} – Shop – LTD/EDN`" />

    <ShopLayout>
        <div class="mb-6">
            <Link href="/shop" class="inline-flex items-center gap-2 text-[0.625rem] font-bold tracking-widest text-white/60 hover:text-white">
                <span aria-hidden="true">←</span> BACK TO SHOP
            </Link>
        </div>

        <section v-if="artist.hero_image" class="mb-10">
            <div class="relative aspect-[16/7] w-full overflow-hidden border-4 border-white/80 bg-neutral-900">
                <img :src="artist.hero_image" :alt="artist.name" class="absolute inset-0 h-full w-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                <div class="absolute bottom-0 left-0 p-6 sm:p-10">
                    <p class="text-[0.625rem] font-bold tracking-widest text-white/70">ARTIST</p>
                    <h1 class="mt-1 font-sans text-3xl font-semibold tracking-tight text-white sm:text-5xl">
                        {{ artist.name.toUpperCase() }}
                    </h1>
                </div>
            </div>
        </section>

        <section v-else class="mb-10 max-w-3xl">
            <p class="text-[0.625rem] font-bold tracking-widest text-white/60">ARTIST</p>
            <h1 class="mt-1 font-sans text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                {{ artist.name.toUpperCase() }}
            </h1>
        </section>

        <section v-if="artist.bio" class="mb-12 max-w-3xl">
            <p class="font-mono text-sm leading-relaxed tracking-wide whitespace-pre-line text-white/75">
                {{ artist.bio }}
            </p>
        </section>

        <section>
            <div class="mb-4 flex items-end justify-between">
                <h2 class="text-[0.625rem] font-bold tracking-widest text-white/60">EDITIONS</h2>
                <p class="text-[0.625rem] font-bold tracking-widest text-white/40">
                    {{ products.length }} {{ products.length === 1 ? 'ITEM' : 'ITEMS' }}
                </p>
            </div>

            <div v-if="products.length === 0" class="border border-white/10 bg-neutral-900/50 p-8 text-center ring-1 ring-white/5">
                <p class="font-mono text-sm tracking-wide text-white/70">No editions from this artist are available right now.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="product in products"
                    :key="product.id"
                    :href="product.shop_url"
                    class="group block focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                >
                    <div
                        class="relative flex aspect-square w-full items-center justify-center overflow-hidden border-4 border-white/80 bg-neutral-900"
                    >
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
                            <h3 class="mt-1 truncate text-sm font-bold tracking-wider text-white">
                                {{ product.name.toUpperCase() }}
                            </h3>
                        </div>
                        <span class="shrink-0 text-[0.625rem] font-bold tracking-widest text-white/60 transition-colors group-hover:text-white">
                            VIEW →
                        </span>
                    </div>
                </Link>
            </div>
        </section>
    </ShopLayout>
</template>
