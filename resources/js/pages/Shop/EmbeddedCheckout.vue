<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { loadStripe, type Stripe } from '@stripe/stripe-js';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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
const emailField = ref<HTMLInputElement | null>(null);
const emailInput = ref('');
const emailError = ref<string | null>(null);
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

const emailMissing = computed(() => emailInput.value.trim() === '');

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
    const value = emailInput.value.trim();

    if (value === '') {
        emailError.value = 'Please enter your email so we can send your receipt.';
        return;
    }

    if (!actions) {
        emailError.value = null;
        return;
    }

    const result = await actions.updateEmail(value);
    if (result?.type === 'error') {
        emailError.value = result.error?.message ?? 'That email does not look right.';
    } else {
        emailError.value = null;
    }
}

async function handleSubmit(): Promise<void> {
    if (submitting.value) return;
    errorMsg.value = null;

    // Validate email ourselves before handing off — Stripe would refuse to
    // confirm without it, but silently (canConfirm stays false, button stays
    // disabled, user has no idea why).
    if (emailMissing.value) {
        emailError.value = 'Please enter your email so we can send your receipt.';
        emailField.value?.focus();
        emailField.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    if (!actions) return;

    submitting.value = true;

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
                    <h2 class="mb-3 text-[0.625rem] font-bold tracking-widest text-white/60">
                        CONTACT <span class="text-red-400" aria-hidden="true">*</span>
                    </h2>
                    <label class="block">
                        <span class="sr-only">Email (required)</span>
                        <input
                            ref="emailField"
                            v-model="emailInput"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="your@email.com (required)"
                            :class="[
                                'h-11 w-full border bg-neutral-950 px-3 font-mono text-sm text-white placeholder:text-white/30 focus:outline-none',
                                emailError ? 'border-red-500/70 focus:border-red-400' : 'border-white/15 focus:border-white/40',
                            ]"
                            @blur="handleEmailBlur"
                            @input="emailError = null"
                        />
                    </label>
                    <p v-if="emailError" class="mt-2 font-mono text-[0.6875rem] tracking-wide text-red-300">
                        {{ emailError }}
                    </p>
                    <p v-else class="mt-2 font-mono text-[0.6875rem] tracking-wide text-white/40">
                        We'll send your receipt and tracking link here.
                    </p>
                </section>

                <section>
                    <h2 class="mb-3 text-[0.625rem] font-bold tracking-widest text-white/60">
                        SHIPPING <span class="text-red-400" aria-hidden="true">*</span>
                    </h2>
                    <div ref="shippingSlot" class="min-h-[160px]"></div>
                </section>
            </div>

            <div class="space-y-6">
                <section>
                    <h2 class="mb-3 text-[0.625rem] font-bold tracking-widest text-white/60">
                        PAYMENT <span class="text-red-400" aria-hidden="true">*</span>
                    </h2>
                    <div v-if="loading" class="flex items-center py-8">
                        <p class="animate-pulse font-mono text-xs tracking-widest text-white/50">LOADING SECURE CHECKOUT…</p>
                    </div>
                    <div ref="paymentSlot" class="min-h-[220px]"></div>
                </section>

                <div v-if="errorMsg" class="border border-red-500/40 bg-red-950/40 p-4 font-mono text-xs tracking-wide text-red-200">
                    {{ errorMsg }}
                </div>

                <!--
                  Button stays clickable even when Stripe's canConfirm is false —
                  if the user pushes it without filling in required fields we can
                  point at what's missing. The disabled greyed-out button we had
                  before told them nothing.
                -->
                <button
                    type="submit"
                    :disabled="submitting"
                    :class="[
                        'h-12 w-full border px-5 text-sm font-extrabold tracking-wider transition-all active:scale-[0.98]',
                        canConfirm && !emailMissing
                            ? 'border-white bg-white text-black hover:bg-neutral-100'
                            : 'border-white/30 bg-white/10 text-white/70 hover:border-white/50 hover:bg-white/15',
                        submitting ? 'cursor-not-allowed opacity-60' : '',
                    ]"
                >
                    {{ submitting ? 'PROCESSING…' : `PAY ${formattedTotal}` }}
                </button>
                <p
                    v-if="!canConfirm || emailMissing"
                    class="text-center font-mono text-[0.6875rem] tracking-wide text-white/50"
                >
                    Finish your email, shipping address, and card details to complete checkout.
                </p>
                <p class="text-center text-[0.625rem] font-bold tracking-widest text-white/40">
                    SECURE PAYMENT PROCESSING VIA STRIPE
                </p>
            </div>
        </form>
    </ShopLayout>
</template>
