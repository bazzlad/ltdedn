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
        <div class="relative z-10 flex min-h-screen items-center justify-center py-8 sm:py-12">
            <div class="container mx-auto px-4">
                <div class="mx-auto max-w-2xl">
                    <!-- Header -->
                    <div class="mb-6 text-center sm:mb-8">
                        <h1 class="mb-2 text-3xl font-extrabold tracking-tight text-white sm:mb-3 sm:text-4xl">Digital Edition</h1>
                        <p class="text-base text-neutral-400 sm:text-lg">Scan complete! Here's what you found:</p>
                    </div>

                        <!-- Edition Card -->
                        <div class="mb-6 overflow-hidden rounded-xl border border-neutral-200/50 bg-white shadow-lg ring-1 ring-black/5 dark:border-neutral-800/50 dark:bg-neutral-900 dark:ring-white/5 sm:mb-8">
                            <!-- Product Image -->
                            <div v-if="edition.product.cover_image" class="aspect-video bg-neutral-100 dark:bg-neutral-800">
                                <img :src="edition.product.cover_image" :alt="edition.product.name" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="flex aspect-video items-center justify-center bg-neutral-100 dark:bg-neutral-800">
                                <div class="text-center text-neutral-400 dark:text-neutral-600">
                                    <svg class="mx-auto mb-2 h-12 w-12 sm:mb-3 sm:h-16 sm:w-16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                                    </svg>
                                    <p class="text-xs font-medium sm:text-sm">No image available</p>
                                </div>
                            </div>

                            <div class="p-5 sm:p-8">
                                <!-- Product Info -->
                                <div class="mb-5 sm:mb-6">
                                    <h2 class="mb-2 text-xl font-extrabold tracking-tight text-neutral-900 sm:text-2xl dark:text-white">
                                        {{ edition.product.name }}
                                    </h2>
                                    <p class="mb-2 text-sm text-neutral-600 sm:mb-3 sm:text-base dark:text-neutral-400">by {{ edition.product.artist.name }}</p>
                                    <p v-if="edition.product.description" class="text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                                        {{ edition.product.description }}
                                    </p>
                                </div>

                                <!-- Edition Details -->
                                <div class="mb-5 rounded-xl bg-neutral-50/50 p-4 ring-1 ring-neutral-200/50 sm:mb-6 sm:p-5 dark:bg-neutral-800/50 dark:ring-neutral-700/50">
                                    <div class="grid grid-cols-2 gap-4 sm:gap-6">
                                        <div>
                                            <p class="mb-1 text-xs font-semibold text-neutral-600 sm:mb-1.5 sm:text-sm dark:text-neutral-400">Edition Number</p>
                                            <p class="text-lg font-extrabold text-neutral-900 sm:text-xl dark:text-white">#{{ edition.number }}</p>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-xs font-semibold text-neutral-600 sm:mb-1.5 sm:text-sm dark:text-neutral-400">Status</p>
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold shadow-sm sm:px-3 sm:py-1"
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
                                    class="mb-5 rounded-xl border border-blue-200/50 bg-blue-50/50 p-4 ring-1 ring-blue-200/50 sm:mb-6 sm:p-5 dark:border-blue-800/50 dark:bg-blue-900/20 dark:ring-blue-800/50"
                                >
                                    <div class="flex items-start gap-2.5 sm:gap-3">
                                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <div>
                                            <h3 class="text-sm font-bold text-blue-900 sm:text-base dark:text-blue-300">
                                                {{ isOwnedByCurrentUser ? 'You own this edition' : 'This edition is already claimed' }}
                                            </h3>
                                            <p class="mt-1 text-xs text-blue-800 sm:text-sm dark:text-blue-400">
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
                        <div class="space-y-6">
                            <!-- Claim Button -->
                            <div v-if="canClaim">
                                <Form
                                    v-if="$page.props.auth.user"
                                    :action="qr.claim(edition.qr_code).url"
                                    method="post"
                                    class="w-full"
                                >
                                    <Button
                                        type="submit"
                                        class="group relative flex h-16 w-full items-center justify-center gap-3 overflow-hidden rounded-xl bg-gradient-to-r from-green-500 to-emerald-600 px-8 text-xl font-extrabold text-white shadow-2xl shadow-green-500/50 transition-all hover:scale-[1.02] hover:shadow-green-500/60 focus:outline-none focus-visible:ring-4 focus-visible:ring-green-400 active:scale-[0.98] sm:h-20 sm:text-2xl"
                                    >
                                        <span class="absolute inset-0 bg-gradient-to-r from-green-400 to-emerald-500 opacity-0 transition-opacity group-hover:opacity-100"></span>
                                        <svg class="relative z-10 h-7 w-7 sm:h-8 sm:w-8" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <span class="relative z-10">Claim This Edition</span>
                                    </Button>
                                </Form>

                                <div v-else class="text-center">
                                    <div class="mb-6 rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 ring-1 ring-yellow-500/20">
                                        <p class="text-base font-semibold text-yellow-200">
                                            🔐 You need to be logged in to claim this edition
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-3 sm:flex-row">
                                        <Link
                                            :href="login({ query: { intended: qr.show(edition.qr_code).url } }).url"
                                            class="flex-1 inline-flex items-center justify-center rounded-xl bg-white px-6 py-4 text-base font-bold text-black shadow-lg transition-all hover:scale-[1.02] hover:bg-neutral-100 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white active:scale-[0.98]"
                                        >
                                            Log In to Claim
                                        </Link>
                                        <Link
                                            :href="register({ query: { intended: qr.show(edition.qr_code).url } }).url"
                                            class="flex-1 inline-flex items-center justify-center rounded-xl px-6 py-4 text-base font-bold text-white ring-2 ring-white/30 transition-all hover:scale-[1.02] hover:bg-white/10 hover:ring-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white active:scale-[0.98]"
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
                                        class="cursor-pointer p-4 text-sm font-semibold text-neutral-300 transition-colors hover:bg-white/10 hover:text-white sm:p-5 sm:text-base"
                                    >
                                        Transfer Edition
                                    </summary>
                                    <div class="border-t border-neutral-200/50 p-4 sm:p-5 dark:border-neutral-800/50">
                                        <Form :action="qr.transfer(edition.qr_code).url" method="post" class="space-y-4">
                                            <div>
                                                <Label for="recipient_email" class="text-sm text-neutral-300">Recipient's Email</Label>
                                                <Input
                                                    id="recipient_email"
                                                    name="recipient_email"
                                                    type="email"
                                                    placeholder="Enter recipient's email address"
                                                    required
                                                    class="mt-2"
                                                />
                                            </div>
                                            <Button type="submit" variant="outline" class="w-full text-sm font-semibold sm:text-base"> Transfer Ownership </Button>
                                        </Form>
                                    </div>
                                </details>
                            </div>

                            <!-- Back to Home -->
                            <Link
                                href="/"
                                class="block text-center text-xs font-medium text-neutral-400 transition-colors hover:text-white sm:text-sm"
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
            cover_image: string | null;
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
