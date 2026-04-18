<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { loadStripe, type Stripe, type StripeEmbeddedCheckout } from '@stripe/stripe-js';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    clientSecret: string;
    publishableKey: string;
    order: {
        id: number;
        total_amount: number;
        currency: string;
    };
}>();

const mountEl = ref<HTMLDivElement | null>(null);
const errorMessage = ref<string | null>(null);
const loading = ref(true);

let stripe: Stripe | null = null;
let checkout: StripeEmbeddedCheckout | null = null;

onMounted(async () => {
    if (!props.publishableKey) {
        errorMessage.value = 'Stripe is not configured — contact support.';
        loading.value = false;
        return;
    }

    try {
        stripe = await loadStripe(props.publishableKey);
        if (!stripe) {
            throw new Error('Stripe.js failed to load.');
        }

        checkout = await stripe.createEmbeddedCheckoutPage({ clientSecret: props.clientSecret });

        if (mountEl.value) {
            checkout.mount(mountEl.value);
        }
    } catch (err) {
        errorMessage.value = err instanceof Error ? err.message : 'Unable to load checkout.';
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    checkout?.destroy();
});

const formattedTotal = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: props.order.currency.toUpperCase(),
}).format(props.order.total_amount / 100);
</script>

<template>
    <Head title="Checkout – LTD/EDN" />

    <ShopLayout :show-cart="false">
        <header class="mb-8 flex items-end justify-between gap-4">
            <div>
                <p class="text-[0.625rem] font-bold tracking-widest text-white/60">ORDER #{{ props.order.id }}</p>
                <h1 class="mt-1 font-sans text-3xl font-semibold tracking-tight text-white sm:text-4xl">CHECKOUT</h1>
                <p class="mt-2 font-mono text-sm tracking-wide text-white/60">Total {{ formattedTotal }}</p>
            </div>
            <Link href="/shop/cart" class="text-[0.625rem] font-bold tracking-widest text-white/60 hover:text-white">← BACK TO CART</Link>
        </header>

        <div v-if="errorMessage" class="border border-red-500/40 bg-red-950/40 p-6 font-mono text-sm tracking-wide text-red-200">
            {{ errorMessage }}
        </div>

        <div v-else class="border border-white/10 bg-neutral-900/50 p-4 ring-1 ring-white/5 sm:p-6">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <p class="animate-pulse font-mono text-xs tracking-widest text-white/50">LOADING SECURE CHECKOUT…</p>
            </div>
            <div ref="mountEl" class="min-h-[520px]"></div>
        </div>

        <p class="mt-4 text-center text-[0.625rem] font-bold tracking-widest text-white/40">SECURE PAYMENT PROCESSING VIA STRIPE</p>
    </ShopLayout>
</template>
