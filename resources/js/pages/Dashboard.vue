<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import qr from '@/routes/qr';
import { type BreadcrumbItem } from '@/types';
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
        cover_image: string | null;
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-neutral-900 dark:text-white">My Digital Collection</h1>
                <p class="mt-2 text-base text-neutral-600 dark:text-neutral-400">
                    {{ ownedEditions.total }} edition{{ ownedEditions.total !== 1 ? 's' : '' }} owned
                </p>
            </div>

            <!-- Editions Grid -->
            <div v-if="ownedEditions.data.length > 0" class="space-y-8">
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="edition in ownedEditions.data"
                        :key="edition.id"
                        class="group overflow-hidden rounded-xl border border-neutral-200/50 bg-white shadow-sm ring-1 ring-black/5 transition-all hover:shadow-lg hover:ring-black/10 dark:border-neutral-800/50 dark:bg-neutral-900 dark:ring-white/5 dark:hover:ring-white/10"
                    >
                        <!-- Product Image -->
                        <div class="relative aspect-video bg-neutral-100 dark:bg-neutral-800">
                            <img
                                v-if="edition.product.cover_image"
                                :src="edition.product.cover_image"
                                :alt="edition.product.name"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center">
                                <div class="text-center text-neutral-400 dark:text-neutral-600">
                                    <svg class="mx-auto mb-2 h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                                    </svg>
                                    <p class="text-sm font-medium">No image</p>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="absolute top-3 right-3">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm backdrop-blur-sm"
                                    :class="{
                                        'bg-green-500/90 text-white': edition.status === 'available',
                                        'bg-blue-500/90 text-white': edition.status === 'sold',
                                        'bg-yellow-500/90 text-white': edition.status === 'pending_transfer',
                                        'bg-neutral-500/90 text-white': !['available', 'sold', 'pending_transfer'].includes(edition.status),
                                    }"
                                >
                                    {{
                                        edition.status === 'sold'
                                            ? 'Owned'
                                            : edition.status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="p-5">
                            <div class="mb-4">
                                <h3 class="mb-1 text-lg font-bold text-neutral-900 dark:text-white">
                                    {{ edition.product.name }}
                                </h3>
                                <p class="mb-1.5 text-sm text-neutral-600 dark:text-neutral-400">by {{ edition.product.artist.name }}</p>
                                <p class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Edition #{{ edition.number }}</p>
                            </div>

                            <div class="flex items-center justify-between border-t border-neutral-100 pt-4 dark:border-neutral-800">
                                <div class="text-xs font-medium text-neutral-500 dark:text-neutral-500">
                                    Acquired {{ new Date(edition.created_at).toLocaleDateString() }}
                                </div>

                                <Link
                                    :href="qr.show(edition.qr_code).url"
                                    class="inline-flex items-center rounded-xl bg-neutral-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-neutral-800 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 dark:bg-white dark:text-black dark:hover:bg-neutral-100 dark:focus-visible:ring-white"
                                >
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                        />
                                    </svg>
                                    View
                                </Link>
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
            <div v-else class="py-20 text-center">
                <div class="mx-auto mb-6 flex h-28 w-28 items-center justify-center rounded-full bg-neutral-100 ring-1 ring-neutral-200/50 dark:bg-neutral-800 dark:ring-neutral-700/50">
                    <svg class="h-14 w-14 text-neutral-400 dark:text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                        />
                    </svg>
                </div>
                <h3 class="mb-2 text-xl font-bold text-neutral-900 dark:text-white">No editions owned yet</h3>
                <p class="mb-1 text-base text-neutral-600 dark:text-neutral-400">
                    Start building your digital collection by scanning QR codes from physical artworks.
                </p>
                <p class="text-sm text-neutral-500 dark:text-neutral-500">
                    Look for QR codes on art prints, collectibles, or other physical items to claim your digital editions.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
