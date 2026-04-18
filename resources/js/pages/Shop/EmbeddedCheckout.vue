<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { loadStripe, type Stripe } from '@stripe/stripe-js';
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

const paymentSlot = ref<HTMLDivElement | null>(null);
const shippingSlot = ref<HTMLDivElement | null>(null);
const emailInput = ref('');
const loading = ref(true);
const submitting = ref(false);
const errorMsg = ref<string | null>(null);
const canConfirm = ref(false);

let stripe: Stripe | null = null;
// Stripe's Checkout Elements SDK is new-ish and its type surface
// isn't complete in every @stripe/stripe-js version, so we type
// loosely here and rely on runtime calls documented by Stripe.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let checkout: any = null;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let actions: any = null;

const formattedTotal = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: props.order.currency.toUpperCase(),
}).format(props.order.total_amount / 100);

onMounted(async () => {
    if (!props.publishableKey) {
        errorMsg.value = 'Stripe is not configured — contact support.';
        loading.value = false;
        return;
    }

    try {
        stripe = await loadStripe(props.publishableKey);
        if (!stripe) {
            throw new Error('Stripe.js failed to load.');
        }

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        checkout = (stripe as any).initCheckoutElementsSdk({
            clientSecret: props.clientSecret,
            elementsOptions: {
                appearance: {
                    theme: 'night',
                    variables: {
                        colorPrimary: '#ffffff',
                        colorBackground: '#0a0a0a',
                        colorText: '#ffffff',
                        fontFamily: 'Instrument Sans, system-ui, sans-serif',
                        borderRadius: '0px',
                    },
                },
            },
        });

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        checkout.on('change', (session: any) => {
            canConfirm.value = !!session?.canConfirm;
        });

        const paymentElement = checkout.createPaymentElement();
        paymentElement.mount(paymentSlot.value);

        const shippingElement = checkout.createShippingAddressElement();
        shippingElement.mount(shippingSlot.value);

        const loaded = await checkout.loadActions();
        actions = loaded.actions;
    } catch (err) {
        errorMsg.value = err instanceof Error ? err.message : 'Unable to load checkout.';
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    try {
        checkout?.getPaymentElement()?.unmount();
        checkout?.getShippingAddressElement()?.unmount();
    } catch {
        // Stripe throws if already unmounted — ignore.
    }
});

async function handleEmailBlur(): Promise<void> {
    if (!emailInput.value || !actions) return;
    const result = await actions.updateEmail(emailInput.value);
    if (result?.type === 'error') {
        errorMsg.value = result.error?.message ?? 'Please enter a valid email.';
    } else {
        errorMsg.value = null;
    }
}

async function handleSubmit(): Promise<void> {
    if (!actions || submitting.value) return;
    submitting.value = true;
    errorMsg.value = null;

    const result = await actions.confirm();
    if (result?.type === 'error') {
        errorMsg.value = result.error?.message ?? 'Payment could not be completed.';
        submitting.value = false;
    }
    // On success, Stripe navigates the browser to the session's return_url.
}
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

        <form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-10" @submit.prevent="handleSubmit">
            <div class="space-y-6">
                <section>
                    <h2 class="mb-3 text-[0.625rem] font-bold tracking-widest text-white/60">CONTACT</h2>
                    <label class="block">
                        <span class="sr-only">Email</span>
                        <input
                            v-model="emailInput"
                            type="email"
                            required
                            placeholder="your@email.com"
                            class="h-11 w-full border border-white/15 bg-neutral-950 px-3 font-mono text-sm text-white placeholder:text-white/30 focus:border-white/40 focus:outline-none"
                            @blur="handleEmailBlur"
                        />
                    </label>
                </section>

                <section>
                    <h2 class="mb-3 text-[0.625rem] font-bold tracking-widest text-white/60">SHIPPING</h2>
                    <div ref="shippingSlot" class="min-h-[160px]"></div>
                </section>
            </div>

            <div class="space-y-6">
                <section>
                    <h2 class="mb-3 text-[0.625rem] font-bold tracking-widest text-white/60">PAYMENT</h2>
                    <div v-if="loading" class="flex items-center py-8">
                        <p class="animate-pulse font-mono text-xs tracking-widest text-white/50">LOADING SECURE CHECKOUT…</p>
                    </div>
                    <div ref="paymentSlot" class="min-h-[220px]"></div>
                </section>

                <div v-if="errorMsg" class="border border-red-500/40 bg-red-950/40 p-4 font-mono text-xs tracking-wide text-red-200">
                    {{ errorMsg }}
                </div>

                <button
                    type="submit"
                    :disabled="!canConfirm || submitting"
                    class="h-12 w-full border border-white bg-white px-5 text-sm font-extrabold tracking-wider text-black transition-all hover:bg-neutral-100 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ submitting ? 'PROCESSING…' : `PAY ${formattedTotal}` }}
                </button>
                <p class="text-center text-[0.625rem] font-bold tracking-widest text-white/40">SECURE PAYMENT PROCESSING VIA STRIPE</p>
            </div>
        </form>
    </ShopLayout>
</template>
