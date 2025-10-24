<template>
    <div class="flex-grow main-bg">
        <!-- background layers -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
            <div class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
        </div>
        <!-- Content Wrapper -->
        <div class="relative z-10">
            <!--<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">-->
            <div class="flex min-h-screen items-center justify-center">
                <div class="container mx-auto px-4 py-8">
                    <div class="mx-auto max-w-2xl">
                        <!-- Header -->
                        <div class="mb-8 text-center">
                            <h1 class="mb-2 text-3xl font-bold text-slate-900 dark:text-slate-100">Digital Edition</h1>
                            <p class="text-slate-600 dark:text-slate-400">Scan complete! Here's what you found:</p>
                        </div>

                        <!-- Edition Card -->
                        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800">
                            <!-- Product Image -->
                            <div v-if="edition.product.cover_image_url" class="aspect-video bg-slate-100 dark:bg-slate-700">
                                <img :src="edition.product.cover_image_url" :alt="edition.product.name" class="h-full w-full object-cover" />
                            </div>
                            <div
                                v-else
                                class="flex aspect-video items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-600"
                            >
                                <div class="text-center text-slate-400 dark:text-slate-500">
                                    <svg class="mx-auto mb-2 h-16 w-16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                                    </svg>
                                    <p class="text-sm">No image available</p>
                                </div>
                            </div>

                            <div class="p-6">
                                <!-- Product Info -->
                                <div class="mb-6">
                                    <h2 class="mb-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                        {{ edition.product.name }}
                                    </h2>
                                    <p class="mb-3 text-slate-600 dark:text-slate-400">by {{ edition.product.artist.name }}</p>
                                    <p v-if="edition.product.description" class="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                                        {{ edition.product.description }}
                                    </p>
                                </div>

                                <!-- Edition Details -->
                                <div class="mb-6 rounded-lg bg-slate-50 p-4 dark:bg-slate-700">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Edition Number</p>
                                            <p class="text-lg font-bold text-slate-900 dark:text-slate-100">#{{ edition.number }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Status</p>
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': edition.status === 'available',
                                                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': edition.status === 'sold',
                                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300':
                                                        edition.status === 'pending_transfer',
                                                    'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300': ![
                                                        'available',
                                                        'sold',
                                                        'pending_transfer',
                                                    ].includes(edition.status),
                                                }"
                                            >
                                                {{ edition.status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase()) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ownership Status -->
                                <div v-if="isClaimed" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                    <div class="flex items-start">
                                        <svg class="mt-0.5 mr-3 h-5 w-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <div>
                                            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">
                                                {{ isOwnedByCurrentUser ? 'You own this edition' : 'This edition is already claimed' }}
                                            </h3>
                                            <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">
                                                {{
                                                    isOwnedByCurrentUser
                                                        ? 'This digital edition belongs to you!'
                                                        : 'This edition has been claimed by another collector.'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- QR Code Info -->
                                <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                                    <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">QR Code Information</p>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-slate-600 dark:text-slate-400">QR Code:</span>
                                        <code class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-800 dark:bg-slate-700 dark:text-slate-200">
                                            {{ edition.qr_code.substring(0, 16) }}...
                                        </code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-4">
                            <!-- Claim Button -->
                            <div v-if="canClaim">
                                <Form v-if="$page.props.auth.user" :action="qr.claim(edition.qr_code).url" method="post" class="w-full">
                                    <Button type="submit" class="w-full bg-green-600 py-4 text-lg font-semibold text-white hover:bg-green-700">
                                        <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        Claim This Edition
                                    </Button>
                                </Form>

                                <div v-else class="text-center">
                                    <p class="mb-4 text-slate-600 dark:text-slate-400">You need to be logged in to claim this edition</p>
                                    <div class="flex justify-center gap-3">
                                        <Link
                                            :href="login({ query: { intended: qr.show(edition.qr_code).url } }).url"
                                            class="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 font-medium text-white transition-colors hover:bg-blue-700"
                                        >
                                            Log In
                                        </Link>
                                        <Link
                                            :href="register({ query: { intended: qr.show(edition.qr_code).url } }).url"
                                            class="inline-flex items-center rounded-lg bg-slate-600 px-6 py-3 font-medium text-white transition-colors hover:bg-slate-700"
                                        >
                                            Sign Up
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Transfer Button (for owners) -->
                            <div v-if="isOwnedByCurrentUser">
                                <details class="rounded-lg bg-slate-100 dark:bg-slate-700">
                                    <summary
                                        class="cursor-pointer rounded-lg p-4 font-medium text-slate-700 transition-colors hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-600"
                                    >
                                        Transfer Edition
                                    </summary>
                                    <div class="p-4 pt-0">
                                        <Form :action="qr.transfer(edition.qr_code).url" method="post" class="space-y-4">
                                            <div>
                                                <Label for="recipient_email">Recipient's Email</Label>
                                                <Input
                                                    id="recipient_email"
                                                    name="recipient_email"
                                                    type="email"
                                                    placeholder="Enter recipient's email address"
                                                    required
                                                />
                                            </div>
                                            <Button type="submit" variant="outline" class="w-full"> Transfer Ownership </Button>
                                        </Form>
                                    </div>
                                </details>
                            </div>

                            <!-- Back to Home -->
                            <Link
                                href="/"
                                class="block text-center text-slate-600 transition-colors hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                            >
                                ← Back to Homepage
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import { login, register } from '@/routes';
import qr from '@/routes/qr';
import { Form, Link } from '@inertiajs/vue3';

interface Props {
    edition: {
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
        owner: null;
    };
    isClaimed: boolean;
    isOwnedByCurrentUser: boolean;
    canClaim: boolean;
}

defineProps<Props>();
</script>
