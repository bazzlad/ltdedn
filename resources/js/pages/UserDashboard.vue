<script setup lang="ts">
import UserLayout from '@/layouts/UserLayout.vue';
import qr from '@/routes/qr';
import transfers from '@/routes/transfers';
import { Head, Link } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';

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
        cover_image: string | null;
        artist: {
            id: number;
            name: string;
        };
    };
}

interface PendingTransfer {
    id: number;
    token: string;
    expires_at: string;
    status: string;
    product_edition: {
        number: number;
        product: {
            name: string;
            cover_image: string | null;
            artist: {
                name: string;
            };
        };
    };
    sender: {
        name: string;
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
    pendingTransfers: PendingTransfer[];
}

defineProps<Props>();
</script>

<template>
    <Head title="My Digital Collection" />

    <UserLayout>
        <!-- Hero Section -->
        <div class="mb-12 text-center">
            <h1 class="mb-4 text-4xl font-extrabold tracking-tight text-neutral-900 sm:text-5xl dark:text-white">My Digital Collection</h1>
            <p class="mx-auto max-w-2xl text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                Your personal gallery of digital art and collectibles. Each piece represents a unique connection between physical and digital
                ownership.
            </p>
            <div class="mt-8 flex items-center justify-center gap-8 text-sm font-medium text-neutral-600 dark:text-neutral-400">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-2 rounded-full bg-neutral-900 dark:bg-white"></div>
                    <span>{{ ownedEditions.total }} Total Editions</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <span>Verified Ownership</span>
                </div>
            </div>
        </div>

        <!-- Pending Transfers Section -->
        <div v-if="pendingTransfers.length > 0" class="mb-12 rounded-2xl border border-yellow-200/50 bg-gradient-to-br from-yellow-50/80 to-orange-50/50 p-8 shadow-lg ring-1 ring-yellow-200/50 dark:border-yellow-800/50 dark:from-yellow-900/20 dark:to-orange-900/20 dark:ring-yellow-800/50">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-yellow-500/20 text-yellow-600 ring-4 ring-yellow-500/10 dark:text-yellow-400">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="mb-2 text-3xl font-extrabold text-yellow-900 dark:text-yellow-200">
                    Pending Transfer{{ pendingTransfers.length !== 1 ? 's' : '' }}
                </h2>
                <p class="text-base text-yellow-800 dark:text-yellow-300">
                    You have {{ pendingTransfers.length }} transfer request{{ pendingTransfers.length !== 1 ? 's' : '' }} waiting for your action
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="transfer in pendingTransfers"
                    :key="transfer.id"
                    class="group overflow-hidden rounded-xl border border-neutral-200/50 bg-white shadow-md ring-1 ring-black/5 transition-all hover:scale-[1.02] hover:shadow-xl dark:border-neutral-800/50 dark:bg-neutral-900 dark:ring-white/5"
                >
                    <!-- Product Image -->
                    <div class="relative bg-neutral-100 dark:bg-neutral-800">
                        <img
                            v-if="transfer.product_edition.product.cover_image"
                            :src="transfer.product_edition.product.cover_image"
                            :alt="transfer.product_edition.product.name"
                            class="h-auto w-full object-contain transition-transform duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex aspect-video h-full w-full items-center justify-center">
                            <svg class="h-12 w-12 text-neutral-400 dark:text-neutral-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                            </svg>
                        </div>

                        <!-- Edition Badge -->
                        <div class="absolute top-3 left-3">
                            <div class="rounded-full bg-black/80 px-3 py-1 text-xs font-bold text-white backdrop-blur-sm">
                                #{{ transfer.product_edition.number }}
                            </div>
                        </div>
                    </div>

                    <!-- Transfer Info -->
                    <div class="p-5">
                        <h3 class="mb-1.5 text-lg font-bold text-neutral-900 dark:text-white">
                            {{ transfer.product_edition.product.name }}
                        </h3>
                        <p class="mb-1 text-sm font-medium text-neutral-600 dark:text-neutral-400">
                            by {{ transfer.product_edition.product.artist.name }}
                        </p>
                        <p class="mb-3 text-sm text-neutral-700 dark:text-neutral-300">
                            From <span class="font-semibold">{{ transfer.sender.name }}</span>
                        </p>
                        <p class="mb-5 text-xs text-neutral-500 dark:text-neutral-500">
                            Expires {{ formatDistanceToNow(new Date(transfer.expires_at), { addSuffix: true }) }}
                        </p>

                        <Link
                            :href="transfers.accept(transfer.token).url"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-green-500 to-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-green-500/30 transition-all hover:scale-105 hover:shadow-green-500/50 focus:outline-none focus-visible:ring-4 focus-visible:ring-green-400"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Review Transfer
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collection Grid -->
        <div v-if="ownedEditions.data.length > 0" class="space-y-12">
            <!-- Featured Grid -->
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="edition in ownedEditions.data"
                    :key="edition.id"
                    class="group relative overflow-hidden rounded-xl border border-neutral-200/50 bg-white shadow-sm ring-1 ring-black/5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:ring-black/10 dark:border-neutral-800/50 dark:bg-neutral-900 dark:ring-white/5 dark:hover:ring-white/10"
                >
                    <!-- Product Image -->
                    <div class="relative overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                        <img
                            v-if="edition.product.cover_image"
                            :src="edition.product.cover_image"
                            :alt="edition.product.name"
                            class="h-auto w-full object-contain transition-transform duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex aspect-[4/5] h-full w-full items-center justify-center">
                            <div class="text-center text-neutral-400 dark:text-neutral-600">
                                <svg class="mx-auto mb-4 h-16 w-16" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                                </svg>
                                <p class="text-sm font-medium">Artwork Preview</p>
                            </div>
                        </div>

                        <!-- Edition Number Badge -->
                        <div class="absolute top-4 left-4">
                            <div class="rounded-full bg-black/80 px-3 py-1 text-xs font-bold text-white shadow-sm backdrop-blur-sm">
                                #{{ edition.number }}
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="absolute top-4 right-4">
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm backdrop-blur-sm"
                                :class="{
                                    'bg-green-500/90 text-white': edition.status === 'available',
                                    'bg-blue-500/90 text-white': edition.status === 'sold',
                                    'bg-yellow-500/90 text-white': edition.status === 'pending_transfer',
                                    'bg-neutral-500/90 text-white': !['available', 'sold', 'pending_transfer'].includes(edition.status),
                                }"
                            >
                                {{ edition.status === 'sold' ? 'OWNED' : edition.status.replace('_', ' ').toUpperCase() }}
                            </span>
                        </div>

                        <!-- Overlay on Hover -->
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                        >
                            <div class="absolute right-4 bottom-4 left-4">
                                <Link
                                    :href="qr.show(edition.qr_code).url"
                                    class="flex w-full items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-black shadow-lg backdrop-blur-sm transition-all hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
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
                    <div class="p-5">
                        <div class="mb-3">
                            <h3 class="mb-1.5 text-lg font-bold text-neutral-900 dark:text-white">
                                {{ edition.product.name }}
                            </h3>
                            <p class="mb-1 text-sm font-medium text-neutral-600 dark:text-neutral-400">by {{ edition.product.artist.name }}</p>
                        </div>

                        <div class="flex items-center justify-between border-t border-neutral-100 pt-3 text-xs dark:border-neutral-800">
                            <span class="font-medium text-neutral-500 dark:text-neutral-500">
                                Acquired {{ new Date(edition.created_at).toLocaleDateString() }}
                            </span>
                            <div class="flex items-center gap-1 text-green-600 dark:text-green-500">
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        fill-rule="evenodd"
                                        d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <span class="font-semibold">Verified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="ownedEditions.last_page > 1" class="flex items-center justify-center gap-2">
                <Link
                    v-for="link in ownedEditions.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :class="[
                        'rounded-xl px-4 py-2 text-sm font-semibold transition-all',
                        link.active
                            ? 'bg-neutral-900 text-white shadow-sm dark:bg-white dark:text-black'
                            : link.url
                              ? 'text-neutral-700 ring-1 ring-neutral-300 hover:bg-neutral-50 dark:text-neutral-300 dark:ring-neutral-700 dark:hover:bg-neutral-800'
                              : 'cursor-not-allowed text-neutral-400 ring-1 ring-neutral-200 dark:text-neutral-600 dark:ring-neutral-800',
                    ]"
                    :disabled="!link.url"
                >
                    {{ link.label }}
                </Link>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="ownedEditions.data.length === 0 && pendingTransfers.length === 0" class="py-20 text-center">
            <div class="mx-auto mb-8 flex h-32 w-32 items-center justify-center rounded-full bg-neutral-100 ring-1 ring-neutral-200/50 dark:bg-neutral-800 dark:ring-neutral-700/50">
                <svg class="h-16 w-16 text-neutral-400 dark:text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    />
                </svg>
            </div>
            <h3 class="mb-4 text-2xl font-extrabold tracking-tight text-neutral-900 dark:text-white">Start Your Collection</h3>
            <p class="mx-auto mb-8 max-w-md text-base leading-relaxed text-neutral-600 dark:text-neutral-400">
                Discover and claim digital editions by scanning QR codes found on physical artworks, collectibles, and exclusive items.
            </p>
            <div class="mx-auto max-w-md rounded-xl border border-neutral-200 bg-neutral-50/50 p-6 ring-1 ring-black/5 dark:border-neutral-800 dark:bg-neutral-900/50 dark:ring-white/5">
                <h4 class="mb-4 font-bold text-neutral-900 dark:text-white">How it works:</h4>
                <div class="space-y-3 text-left text-sm text-neutral-600 dark:text-neutral-400">
                    <div class="flex items-start gap-3">
                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-neutral-900 text-xs font-bold text-white dark:bg-white dark:text-black">
                            1
                        </div>
                        <span>Find QR codes on physical art pieces or collectibles</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-neutral-900 text-xs font-bold text-white dark:bg-white dark:text-black">
                            2
                        </div>
                        <span>Scan the code with your phone or camera</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-neutral-900 text-xs font-bold text-white dark:bg-white dark:text-black">
                            3
                        </div>
                        <span>Claim your digital edition and add it to your collection</span>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
