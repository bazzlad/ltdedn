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
        <div class="relative z-10 flex h-screen items-center justify-center">
            <div class="container mx-auto h-full max-h-screen overflow-y-auto px-4 py-8 sm:py-12">
                <div class="mx-auto max-w-lg">
                        <!-- Edition Card -->
                        <div class="mb-4 overflow-hidden rounded-xl border border-neutral-200/50 bg-white shadow-lg ring-1 ring-black/5 sm:mb-6 dark:border-neutral-800/50 dark:bg-neutral-900 dark:ring-white/5">
                            <!-- Product Image -->
                            <div v-if="edition.product.cover_image" class="bg-neutral-100 dark:bg-neutral-800">
                                <img :src="edition.product.cover_image" :alt="edition.product.name" class="h-auto w-full object-contain" />
                            </div>
                            <div v-else class="flex aspect-video items-center justify-center bg-neutral-100 dark:bg-neutral-800">
                                <div class="text-center text-neutral-400 dark:text-neutral-600">
                                    <svg class="mx-auto mb-2 h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                                    </svg>
                                    <p class="text-sm font-medium">No image available</p>
                                </div>
                            </div>

                            <div class="p-4 sm:p-5">
                                <!-- Product Info -->
                                <div class="mb-3 sm:mb-4">
                                    <h2 class="mb-1 text-lg font-extrabold tracking-tight text-neutral-900 sm:text-xl dark:text-white">
                                        {{ edition.product.name }}
                                    </h2>
                                    <p class="text-sm text-neutral-600 dark:text-neutral-400">by {{ edition.product.artist.name }}</p>
                                </div>

                                <!-- Edition Details -->
                                <div class="mb-3 rounded-lg bg-neutral-50/50 p-3 ring-1 ring-neutral-200/50 sm:mb-4 dark:bg-neutral-800/50 dark:ring-neutral-700/50">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <p class="mb-0.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400">Edition</p>
                                            <p class="text-base font-extrabold text-neutral-900 sm:text-lg dark:text-white">#{{ edition.number }}</p>
                                        </div>
                                        <div>
                                            <p class="mb-0.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400">Status</p>
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold shadow-sm"
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
                                    class="rounded-lg border border-blue-200/50 bg-blue-50/50 p-2.5 ring-1 ring-blue-200/50 sm:p-3 dark:border-blue-800/50 dark:bg-blue-900/20 dark:ring-blue-800/50"
                                >
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 flex-shrink-0 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <p class="text-xs font-semibold text-blue-900 sm:text-sm dark:text-blue-300">
                                            {{ isOwnedByCurrentUser ? 'You own this edition' : 'Already claimed' }}
                                        </p>
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
                                    :action="qrClaim(edition.qr_code).url"
                                    method="post"
                                    class="w-full"
                                    #default="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        class="group relative flex h-14 w-full items-center justify-center gap-2.5 overflow-hidden rounded-xl bg-gradient-to-r from-green-500 to-emerald-600 px-6 text-lg font-extrabold text-white shadow-2xl shadow-green-500/50 transition-all hover:scale-[1.02] hover:shadow-green-500/60 focus:outline-none focus-visible:ring-4 focus-visible:ring-green-400 active:scale-[0.98] sm:h-16 sm:gap-3 sm:text-xl disabled:cursor-not-allowed disabled:opacity-75"
                                    >
                                        <span class="absolute inset-0 bg-gradient-to-r from-green-400 to-emerald-500 opacity-0 transition-opacity group-hover:opacity-100"></span>
                                        <svg v-if="!processing" class="relative z-10 h-6 w-6 sm:h-7 sm:w-7" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <svg v-else class="relative z-10 h-6 w-6 animate-spin sm:h-7 sm:w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="relative z-10">{{ processing ? 'Claiming...' : 'Claim This Edition' }}</span>
                                    </Button>
                                </Form>

                                <div v-else class="text-center">
                                    <div class="mb-4 rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-3 ring-1 ring-yellow-500/20">
                                        <p class="text-sm font-semibold text-yellow-200">
                                            You need to be logged in to claim this edition
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-2.5 sm:flex-row sm:gap-3">
                                        <Link
                                            :href="login({ query: { intended: qrShow(edition.qr_code).url } }).url"
                                            class="inline-flex flex-1 items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-bold text-black shadow-lg transition-all hover:scale-[1.02] hover:bg-neutral-100 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white active:scale-[0.98] sm:px-6 sm:text-base"
                                        >
                                            Log In to Claim
                                        </Link>
                                        <Link
                                            :href="register({ query: { intended: qrShow(edition.qr_code).url } }).url"
                                            class="inline-flex flex-1 items-center justify-center rounded-xl px-4 py-3 text-sm font-bold text-white ring-2 ring-white/30 transition-all hover:scale-[1.02] hover:bg-white/10 hover:ring-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white active:scale-[0.98] sm:px-6 sm:text-base"
                                        >
                                            Sign Up
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Transfer State -->
                            <div v-if="isOwnedByCurrentUser && activeTransfer" class="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-4 ring-1 ring-yellow-500/20 backdrop-blur-sm">
                                <div class="mb-3 text-center">
                                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-yellow-500/20 text-yellow-400">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-bold text-yellow-200">Transfer Pending</h3>
                                    <p class="mt-0.5 text-xs text-yellow-200/80">
                                        Waiting for {{ activeTransfer.recipient.name }} to accept.
                                    </p>
                                </div>
                                <Form
                                    :action="transfersCancel(activeTransfer.token).url"
                                    method="post"
                                    #default="{ processing, submit }"
                                >
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        class="w-full justify-center"
                                        :disabled="processing"
                                        @click="() => handleCancelTransfer(submit)"
                                    >
                                        {{ processing ? 'Cancelling...' : 'Cancel Transfer' }}
                                    </Button>
                                </Form>
                            </div>

                            <!-- Transfer Button (for owners) -->
                            <div v-else-if="isOwnedByCurrentUser">
                                <details class="overflow-hidden rounded-lg border border-neutral-200/50 bg-white/5 ring-1 ring-neutral-200/50 backdrop-blur-sm dark:border-neutral-800/50 dark:ring-neutral-800/50">
                                    <summary
                                        class="cursor-pointer p-3 text-sm font-semibold text-neutral-300 transition-colors hover:bg-white/10 hover:text-white"
                                    >
                                        Transfer Edition
                                    </summary>
                                    <div class="border-t border-neutral-200/50 p-3 dark:border-neutral-800/50">
                                        <Form
                                            :action="qrTransfer(edition.qr_code).url"
                                            method="post"
                                            class="space-y-3"
                                            #default="{ processing }"
                                        >
                                            <div>
                                                <Label for="recipient_email" class="text-xs text-neutral-300">Recipient's Email</Label>
                                                <Input
                                                    id="recipient_email"
                                                    name="recipient_email"
                                                    type="email"
                                                    placeholder="Enter email address"
                                                    required
                                                    class="mt-1.5"
                                                />
                                                <p class="mt-1.5 text-xs text-neutral-400">
                                                    Recipient must have an existing account.
                                                </p>
                                            </div>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                class="w-full justify-center text-sm font-semibold"
                                                :disabled="processing"
                                            >
                                                <span v-if="processing" class="mr-2">
                                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </span>
                                                {{ processing ? 'Sending Request...' : 'Transfer Ownership' }}
                                            </Button>
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
import { claim as qrClaim, show as qrShow, transfer as qrTransfer } from '@/routes/qr';
import { cancel as transfersCancel } from '@/routes/transfers';
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
    activeTransfer?: {
        token: string;
        recipient: {
            name: string;
        };
    } | null;
}

defineProps<Props>();

const handleCancelTransfer = (submit: (e?: Event) => void) => {
    if (confirm('Are you sure you want to cancel this transfer? The recipient will be notified.')) {
        submit();
    }
};
</script>
