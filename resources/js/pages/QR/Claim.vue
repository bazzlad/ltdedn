<template>
    <div class="main-bg relative flex min-h-screen flex-col bg-black text-neutral-200 antialiased">
        <!-- background layers -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
            <div
                class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"
            ></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
        </div>

        <!-- Content Wrapper -->
        <div class="relative z-10 flex min-h-screen items-center justify-center">
            <div class="container mx-auto px-4 py-12">
                <div class="mx-auto max-w-2xl">
                    <!-- Header -->
                    <div class="mb-8 text-center">
                        <h1 class="mb-3 text-4xl font-extrabold tracking-tight text-white">Digital Edition</h1>
                        <p class="text-lg text-neutral-400">Scan complete! Here's what you found:</p>
                    </div>

                        <!-- Edition Card -->
                        <div class="mb-8 overflow-hidden rounded-xl border border-neutral-200/50 bg-white shadow-lg ring-1 ring-black/5 dark:border-neutral-800/50 dark:bg-neutral-900 dark:ring-white/5">
                            <!-- Product Image -->
                            <div v-if="edition.product.cover_image_url" class="aspect-video bg-neutral-100 dark:bg-neutral-800">
                                <img :src="edition.product.cover_image_url" :alt="edition.product.name" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="flex aspect-video items-center justify-center bg-neutral-100 dark:bg-neutral-800">
                                <div class="text-center text-neutral-400 dark:text-neutral-600">
                                    <svg class="mx-auto mb-3 h-16 w-16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                                    </svg>
                                    <p class="text-sm font-medium">No image available</p>
                                </div>
                            </div>

                            <div class="p-6 sm:p-8">
                                <!-- Product Info -->
                                <div class="mb-6">
                                    <h2 class="mb-2 text-2xl font-extrabold tracking-tight text-neutral-900 dark:text-white">
                                        {{ edition.product.name }}
                                    </h2>
                                    <p class="mb-3 text-base text-neutral-600 dark:text-neutral-400">by {{ edition.product.artist.name }}</p>
                                    <p v-if="edition.product.description" class="text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                                        {{ edition.product.description }}
                                    </p>
                                </div>

                                <!-- Edition Details -->
                                <div class="mb-6 rounded-xl bg-neutral-50/50 p-5 ring-1 ring-neutral-200/50 dark:bg-neutral-800/50 dark:ring-neutral-700/50">
                                    <div class="grid grid-cols-2 gap-6">
                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-neutral-600 dark:text-neutral-400">Edition Number</p>
                                            <p class="text-xl font-extrabold text-neutral-900 dark:text-white">#{{ edition.number }}</p>
                                        </div>
                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-neutral-600 dark:text-neutral-400">Status</p>
                                            <span
                                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm"
                                                :class="{
                                                    'bg-green-500/90 text-white': edition.status === 'available',
                                                    'bg-blue-500/90 text-white': edition.status === 'sold',
                                                    'bg-yellow-500/90 text-white': edition.status === 'pending_transfer',
                                                    'bg-neutral-500/90 text-white': !['available', 'sold', 'pending_transfer'].includes(edition.status),
                                                }"
                                            >
                                                {{ edition.status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase()) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ownership Status -->
                                <div
                                    v-if="isClaimed"
                                    class="mb-6 rounded-xl border border-blue-200/50 bg-blue-50/50 p-5 ring-1 ring-blue-200/50 dark:border-blue-800/50 dark:bg-blue-900/20 dark:ring-blue-800/50"
                                >
                                    <div class="flex items-start gap-3">
                                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <div>
                                            <h3 class="font-bold text-blue-900 dark:text-blue-300">
                                                {{ isOwnedByCurrentUser ? 'You own this edition' : 'This edition is already claimed' }}
                                            </h3>
                                            <p class="mt-1 text-sm text-blue-800 dark:text-blue-400">
                                                {{
                                                    isOwnedByCurrentUser
                                                        ? 'This digital edition belongs to you!'
                                                        : 'This edition has been claimed by another collector.'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-4">
                            <!-- Claim Button -->
                            <div v-if="canClaim">
                                <Form
                                    v-if="$page.props.auth.user"
                                    :action="qr.claim(edition.qr_code).url"
                                    method="post"
                                    class="flex w-full justify-center"
                                >
                                    <Button
                                        type="submit"
                                        class="flex h-14 w-full max-w-md items-center justify-center gap-2 rounded-xl bg-white px-6 text-base font-bold text-black shadow-lg transition-all hover:bg-neutral-100 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                    >
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
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
                                    <p class="mb-6 text-base text-neutral-400">You need to be logged in to claim this edition</p>
                                    <div class="flex flex-col justify-center gap-3 sm:flex-row">
                                        <Link
                                            :href="login({ query: { intended: qr.show(edition.qr_code).url } }).url"
                                            class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-3 font-semibold text-black shadow-sm transition-all hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                        >
                                            Log In
                                        </Link>
                                        <Link
                                            :href="register({ query: { intended: qr.show(edition.qr_code).url } }).url"
                                            class="inline-flex items-center justify-center rounded-xl px-6 py-3 font-semibold text-white ring-1 ring-white/20 transition-all hover:bg-white/10 hover:ring-white/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                        >
                                            Sign Up
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Transfer Button (for owners) -->
                            <div v-if="isOwnedByCurrentUser">
                                <details class="overflow-hidden rounded-xl border border-neutral-200/50 bg-white/5 ring-1 ring-neutral-200/50 backdrop-blur-sm dark:border-neutral-800/50 dark:ring-neutral-800/50">
                                    <summary
                                        class="cursor-pointer p-5 font-semibold text-neutral-300 transition-colors hover:bg-white/10 hover:text-white"
                                    >
                                        Transfer Edition
                                    </summary>
                                    <div class="border-t border-neutral-200/50 p-5 dark:border-neutral-800/50">
                                        <Form :action="qr.transfer(edition.qr_code).url" method="post" class="space-y-4">
                                            <div>
                                                <Label for="recipient_email" class="text-neutral-300">Recipient's Email</Label>
                                                <Input
                                                    id="recipient_email"
                                                    name="recipient_email"
                                                    type="email"
                                                    placeholder="Enter recipient's email address"
                                                    required
                                                    class="mt-2"
                                                />
                                            </div>
                                            <Button type="submit" variant="outline" class="w-full font-semibold"> Transfer Ownership </Button>
                                        </Form>
                                    </div>
                                </details>
                            </div>

                            <!-- Back to Home -->
                            <Link
                                href="/"
                                class="block text-center text-sm font-medium text-neutral-400 transition-colors hover:text-white"
                            >
                                ← Back to Homepage
                            </Link>
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
