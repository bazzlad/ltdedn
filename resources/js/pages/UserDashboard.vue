<script setup lang="ts">
import UserLayout from '@/layouts/UserLayout.vue';
import qr from '@/routes/qr';
import { Head, Link } from '@inertiajs/vue3';

interface ProductEdition {
    id: number;
    number: number;
    status: string;
    qr_code: string;
    created_at: string;
    product: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        cover_image_url: string | null;
        artist: {
            id: number;
            name: string;
        };
    };
}

interface Props {
    ownedEditions: {
        data: ProductEdition[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        total: number;
    };
}

defineProps<Props>();
</script>

<template>
    <Head title="My Digital Collection" />

    <UserLayout>
        <!-- Hero Section -->
        <div class="mb-12 text-center">
            <h1 class="mb-4 text-4xl font-bold text-slate-900 dark:text-slate-100 sm:text-5xl">
                My Digital Collection
            </h1>
            <p class="mx-auto max-w-2xl text-lg text-slate-600 dark:text-slate-400">
                Your personal gallery of digital art and collectibles. Each piece represents a unique connection between physical and digital ownership.
            </p>
            <div class="mt-6 flex items-center justify-center space-x-8 text-sm text-slate-500 dark:text-slate-400">
                <div class="flex items-center space-x-2">
                    <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                    <span>{{ ownedEditions.total }} Total Editions</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <span>Verified Ownership</span>
                </div>
            </div>
        </div>

        <!-- Collection Grid -->
        <div v-if="ownedEditions.data.length > 0" class="space-y-12">
            <!-- Featured Grid -->
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="edition in ownedEditions.data"
                    :key="edition.id"
                    class="group relative overflow-hidden rounded-2xl bg-white shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 dark:bg-slate-800"
                >
                    <!-- Product Image -->
                    <div class="relative aspect-[4/5] overflow-hidden bg-slate-100 dark:bg-slate-700">
                        <img
                            v-if="edition.product.cover_image_url"
                            :src="edition.product.cover_image_url"
                            :alt="edition.product.name"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <div class="text-center text-slate-400 dark:text-slate-500">
                                <svg class="mx-auto mb-4 h-16 w-16" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                                </svg>
                                <p class="text-sm font-medium">Artwork Preview</p>
                            </div>
                        </div>

                        <!-- Edition Number Badge -->
                        <div class="absolute top-4 left-4">
                            <div class="rounded-full bg-black/70 px-3 py-1 text-xs font-bold text-white backdrop-blur-sm">
                                #{{ edition.number }}
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="absolute top-4 right-4">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold backdrop-blur-sm"
                                :class="{
                                    'bg-green-500/90 text-white': edition.status === 'available',
                                    'bg-blue-500/90 text-white': edition.status === 'sold',
                                    'bg-yellow-500/90 text-white': edition.status === 'pending_transfer',
                                    'bg-gray-500/90 text-white': ![
                                        'available',
                                        'sold',
                                        'pending_transfer',
                                    ].includes(edition.status),
                                }"
                            >
                                {{
                                    edition.status === 'sold'
                                        ? 'OWNED'
                                        : edition.status.replace('_', ' ').toUpperCase()
                                }}
                            </span>
                        </div>

                        <!-- Overlay on Hover -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                            <div class="absolute bottom-4 left-4 right-4">
                                <Link
                                    :href="qr.show(edition.qr_code).url"
                                    class="flex w-full items-center justify-center rounded-lg bg-white/90 px-4 py-2 text-sm font-bold text-slate-900 backdrop-blur-sm transition-all duration-200 hover:bg-white"
                                >
                                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                        />
                                    </svg>
                                    View Details
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="p-6">
                        <div class="mb-4">
                            <h3 class="mb-2 text-lg font-bold text-slate-900 dark:text-slate-100">
                                {{ edition.product.name }}
                            </h3>
                            <p class="mb-1 text-sm font-medium text-slate-600 dark:text-slate-400">
                                by {{ edition.product.artist.name }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>Acquired {{ new Date(edition.created_at).toLocaleDateString() }}</span>
                            <div class="flex items-center space-x-1">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Verified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="ownedEditions.last_page > 1" class="flex items-center justify-center space-x-2">
                <Link
                    v-for="link in ownedEditions.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :class="[
                        'rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200',
                        link.active
                            ? 'bg-blue-600 text-white shadow-lg'
                            : link.url
                              ? 'bg-white text-slate-700 shadow-sm hover:bg-slate-50 hover:shadow-md dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                              : 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500',
                    ]"
                    :disabled="!link.url"
                    v-html="link.label"
                />
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="py-20 text-center">
            <div class="mx-auto mb-8 flex h-32 w-32 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900/20 dark:to-purple-900/20">
                <svg class="h-16 w-16 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    />
                </svg>
            </div>
            <h3 class="mb-4 text-2xl font-bold text-slate-900 dark:text-slate-100">Start Your Collection</h3>
            <p class="mx-auto mb-8 max-w-md text-slate-600 dark:text-slate-400">
                Discover and claim digital editions by scanning QR codes found on physical artworks, collectibles, and exclusive items.
            </p>
            <div class="rounded-xl bg-slate-50 p-6 dark:bg-slate-800/50">
                <h4 class="mb-3 font-semibold text-slate-900 dark:text-slate-100">How it works:</h4>
                <div class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                    <div class="flex items-center space-x-3">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">1</div>
                        <span>Find QR codes on physical art pieces or collectibles</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">2</div>
                        <span>Scan the code with your phone or camera</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">3</div>
                        <span>Claim your digital edition and add it to your collection</span>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
