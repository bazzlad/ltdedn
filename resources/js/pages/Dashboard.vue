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
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">My Digital Collection</h1>
                <p class="mt-1 text-slate-600 dark:text-slate-400">
                    {{ ownedEditions.total }} edition{{ ownedEditions.total !== 1 ? 's' : '' }} owned
                </p>
            </div>

            <!-- Editions Grid -->
            <div v-if="ownedEditions.data.length > 0" class="space-y-6">
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="edition in ownedEditions.data"
                        :key="edition.id"
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <!-- Product Image -->
                        <div class="relative aspect-video bg-slate-100 dark:bg-slate-700">
                            <img
                                v-if="edition.product.cover_image_url"
                                :src="edition.product.cover_image_url"
                                :alt="edition.product.name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center">
                                <div class="text-center text-slate-400 dark:text-slate-500">
                                    <svg class="mx-auto mb-2 h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                                    </svg>
                                    <p class="text-sm">No image</p>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="absolute top-3 right-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': edition.status === 'available',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300': edition.status === 'sold',
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300':
                                            edition.status === 'pending_transfer',
                                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300': ![
                                            'available',
                                            'sold',
                                            'pending_transfer',
                                        ].includes(edition.status),
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
                        <div class="p-4">
                            <div class="mb-3">
                                <h3 class="mb-1 font-semibold text-slate-900 dark:text-slate-100">
                                    {{ edition.product.name }}
                                </h3>
                                <p class="mb-1 text-sm text-slate-600 dark:text-slate-400">by {{ edition.product.artist.name }}</p>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Edition #{{ edition.number }}</p>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    Acquired {{ new Date(edition.created_at).toLocaleDateString() }}
                                </div>

                                <Link
                                    :href="qr.show(edition.qr_code).url"
                                    class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-blue-700"
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
                <div v-if="ownedEditions.last_page > 1" class="flex items-center justify-center space-x-1">
                    <Link
                        v-for="link in ownedEditions.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            link.active
                                ? 'bg-blue-600 text-white'
                                : link.url
                                  ? 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700'
                                  : 'cursor-not-allowed text-slate-400 dark:text-slate-500',
                        ]"
                        :disabled="!link.url"
                        v-html="link.label"
                    />
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="py-12 text-center">
                <div class="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                    <svg class="h-12 w-12 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                        />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-medium text-slate-900 dark:text-slate-100">No editions owned yet</h3>
                <p class="mb-4 text-slate-600 dark:text-slate-400">
                    Start building your digital collection by scanning QR codes from physical artworks.
                </p>
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    Look for QR codes on art prints, collectibles, or other physical items to claim your digital editions.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
