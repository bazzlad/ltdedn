<template>
    <div class="main-bg relative flex min-h-screen flex-col bg-black text-neutral-200 antialiased">
        <Head title="Accept Transfer" />

        <!-- Background layers -->
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
                <div class="mx-auto max-w-3xl">
                    <!-- Header -->
                    <div class="mb-6 text-center sm:mb-8">
                        <h1 class="mb-2 text-3xl font-extrabold tracking-tight text-white sm:mb-3 sm:text-4xl">Transfer Request</h1>
                        <p class="text-base text-neutral-400 sm:text-lg">You've been offered a digital edition!</p>
                    </div>

                    <!-- Transfer Card -->
                    <div class="mb-6 overflow-hidden rounded-xl border border-neutral-200/50 bg-white shadow-lg ring-1 ring-black/5 dark:border-neutral-800/50 dark:bg-neutral-900 dark:ring-white/5 sm:mb-8">
                        <!-- Product Image -->
                        <div v-if="transfer.product_edition.product.cover_image" class="aspect-video bg-neutral-100 dark:bg-neutral-800">
                            <img
                                :src="transfer.product_edition.product.cover_image"
                                :alt="transfer.product_edition.product.name"
                                class="h-full w-full object-cover"
                            />
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
                                    {{ transfer.product_edition.product.name }}
                                </h2>
                                <p class="mb-2 text-sm text-neutral-600 sm:mb-3 sm:text-base dark:text-neutral-400">
                                    by {{ transfer.product_edition.product.artist.name }}
                                </p>
                            </div>

                            <!-- Edition Details -->
                            <div class="mb-5 rounded-xl bg-neutral-50/50 p-4 ring-1 ring-neutral-200/50 sm:mb-6 sm:p-5 dark:bg-neutral-800/50 dark:ring-neutral-700/50">
                                <div class="grid grid-cols-1 gap-4 sm:gap-6">
                                    <div>
                                        <p class="mb-1 text-xs font-semibold text-neutral-600 sm:mb-1.5 sm:text-sm dark:text-neutral-400">Edition Number</p>
                                        <p class="text-lg font-extrabold text-neutral-900 sm:text-xl dark:text-white">#{{ transfer.product_edition.number }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Transfer Info -->
                            <div class="mb-5 rounded-xl border border-blue-200/50 bg-blue-50/50 p-4 ring-1 ring-blue-200/50 sm:mb-6 sm:p-5 dark:border-blue-800/50 dark:bg-blue-900/20 dark:ring-blue-800/50">
                                <div class="flex items-start gap-2.5 sm:gap-3">
                                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div class="flex-1">
                                        <h3 class="text-sm font-bold text-blue-900 sm:text-base dark:text-blue-300">
                                            Transfer from {{ transfer.sender.name }}
                                        </h3>
                                        <p class="mt-1 text-xs text-blue-800 sm:text-sm dark:text-blue-400">
                                            This offer expires {{ timeRemaining }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-4">
                        <!-- Accept Button -->
                        <Button
                            @click="acceptTransfer"
                            :disabled="acceptForm.processing || rejectForm.processing"
                            class="group relative flex h-16 w-full items-center justify-center gap-3 overflow-hidden rounded-xl bg-gradient-to-r from-green-500 to-emerald-600 px-8 text-xl font-extrabold text-white shadow-2xl shadow-green-500/50 transition-all hover:scale-[1.02] hover:shadow-green-500/60 focus:outline-none focus-visible:ring-4 focus-visible:ring-green-400 active:scale-[0.98] sm:h-20 sm:text-2xl disabled:opacity-75 disabled:cursor-not-allowed"
                        >
                            <span class="absolute inset-0 bg-gradient-to-r from-green-400 to-emerald-500 opacity-0 transition-opacity group-hover:opacity-100"></span>
                            <svg v-if="!acceptForm.processing" class="relative z-10 h-7 w-7 sm:h-8 sm:w-8" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <svg v-else class="animate-spin relative z-10 h-7 w-7 sm:h-8 sm:w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="relative z-10">{{ acceptForm.processing ? 'Accepting...' : 'Accept Transfer' }}</span>
                        </Button>

                        <!-- Reject Button -->
                        <Button
                            @click="rejectTransfer"
                            variant="outline"
                            :disabled="acceptForm.processing || rejectForm.processing"
                            class="group relative flex h-12 w-full items-center justify-center gap-2.5 overflow-hidden rounded-xl border-2 border-red-500/30 bg-transparent px-6 text-base font-bold text-red-400 transition-all hover:scale-[1.02] hover:border-red-500/50 hover:bg-red-500/10 focus:outline-none focus-visible:ring-4 focus-visible:ring-red-400/50 active:scale-[0.98] sm:h-14 sm:text-lg disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg v-if="!rejectForm.processing" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <svg v-else class="animate-spin h-5 w-5 sm:h-6 sm:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ rejectForm.processing ? 'Rejecting...' : 'Reject Transfer' }}</span>
                        </Button>

                        <!-- Back to Dashboard -->
                        <Link
                            href="/"
                            class="block text-center text-xs font-medium text-neutral-400 transition-colors hover:text-white sm:text-sm"
                        >
                            ← Back to Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import { computed } from 'vue';
import { formatDistanceToNow } from 'date-fns';
import transfers from '@/routes/transfers';

const props = defineProps<{
    transfer: {
        id: number;
        token: string;
        expires_at: string;
        status: string;
        product_edition: {
            number: number;
            product: {
                name: string;
                cover_image: string;
                artist: {
                    name: string;
                };
            };
        };
        sender: {
            name: string;
        };
    };
}>();

const acceptForm = useForm({});
const rejectForm = useForm({});

const acceptTransfer = () => {
    acceptForm.post(transfers.accept(props.transfer.token).url);
};

const rejectTransfer = () => {
    if (window.confirm('Are you sure you want to reject this transfer? The edition will be returned to the sender.')) {
        rejectForm.post(transfers.reject(props.transfer.token).url);
    }
};

const timeRemaining = computed(() => {
    return formatDistanceToNow(new Date(props.transfer.expires_at), { addSuffix: true });
});
</script>
