<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    status: 'success' | 'cancel';
    order: {
        id: number;
        status: string;
    };
}>();

const isSuccess = computed(() => props.status === 'success');
const orderNumber = computed(() => String(props.order.id).padStart(6, '0'));
</script>

<template>
    <Head :title="`${isSuccess ? 'Order confirmed' : 'Checkout cancelled'} – LTD/EDN`" />

    <ShopLayout>
        <div class="mx-auto max-w-xl py-8 sm:py-12">
            <div
                :class="[
                    'flex items-center gap-3 border p-4',
                    isSuccess
                        ? 'border-green-500/30 bg-green-500/10 ring-1 ring-green-500/20'
                        : 'border-yellow-500/30 bg-yellow-500/10 ring-1 ring-yellow-500/20',
                ]"
            >
                <svg v-if="isSuccess" class="h-5 w-5 shrink-0 text-green-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd"
                    />
                </svg>
                <svg
                    v-else
                    class="h-5 w-5 shrink-0 text-yellow-300"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                    />
                </svg>
                <p :class="['text-xs font-bold tracking-widest', isSuccess ? 'text-green-200' : 'text-yellow-200']">
                    {{ isSuccess ? 'PAYMENT RECEIVED' : 'CHECKOUT CANCELLED' }}
                </p>
            </div>

            <h1 class="mt-8 font-sans text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                {{ isSuccess ? 'Thanks for your order' : 'Checkout cancelled' }}
            </h1>

            <p class="mt-4 font-mono text-sm leading-relaxed tracking-wide text-white/75">
                <span v-if="isSuccess">
                    We've received your order and will email a confirmation once payment is fully processed. Your limited edition is now reserved —
                    we'll ship it with a QR-linked certificate of provenance.
                </span>
                <span v-else> Your cart is still saved. You can finish checking out whenever you're ready. </span>
            </p>

            <div class="mt-6 grid grid-cols-2 gap-4 border border-white/10 bg-neutral-900/80 p-5 ring-1 ring-white/10">
                <div>
                    <p class="text-[0.625rem] font-bold tracking-widest text-neutral-500">ORDER NUMBER</p>
                    <p class="mt-1 font-mono text-lg font-extrabold tracking-tight text-white">#{{ orderNumber }}</p>
                </div>
                <div>
                    <p class="text-[0.625rem] font-bold tracking-widest text-neutral-500">STATUS</p>
                    <span
                        :class="[
                            'mt-1 inline-flex items-center px-3 py-1 text-[0.625rem] font-bold tracking-widest',
                            props.order.status === 'paid'
                                ? 'bg-green-600 text-white'
                                : props.order.status === 'pending'
                                  ? 'bg-yellow-600 text-white'
                                  : 'bg-neutral-600 text-white',
                        ]"
                    >
                        {{ props.order.status.toUpperCase() }}
                    </span>
                </div>
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link
                    href="/shop"
                    class="inline-flex h-12 flex-1 items-center justify-center border border-white bg-white px-5 text-sm font-extrabold tracking-wider text-black transition-all hover:bg-neutral-100 active:scale-[0.98]"
                >
                    BACK TO SHOP
                </Link>
                <Link
                    v-if="!isSuccess"
                    href="/cart"
                    class="inline-flex h-12 flex-1 items-center justify-center border border-white/30 px-5 text-sm font-extrabold tracking-wider text-white transition-all hover:border-white/60 hover:bg-white/10 active:scale-[0.98]"
                >
                    RETURN TO CART
                </Link>
            </div>
        </div>
    </ShopLayout>
</template>
