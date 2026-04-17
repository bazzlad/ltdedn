<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type CartLine = {
    id: number;
    product_id: number;
    product_sku_id: number | null;
    product_name: string;
    sku_code: string | null;
    quantity: number;
    unit_amount: number;
    line_total: number;
    available: number | null;
    ok: boolean;
    error: string | null;
};

type CartSnapshot = {
    currency: string;
    subtotal: number;
    item_count: number;
    lines: CartLine[];
};

const props = defineProps<{
    cart: CartSnapshot;
}>();

const page = usePage();
const isAuthed = computed(() => {
    const auth = (page.props as Record<string, unknown>).auth as { user: unknown } | undefined;
    return auth?.user != null;
});

const busyLines = ref<Set<number>>(new Set());

function isBusy(lineId: number): boolean {
    return busyLines.value.has(lineId);
}

function markBusy(lineId: number): void {
    const next = new Set(busyLines.value);
    next.add(lineId);
    busyLines.value = next;
}

function clearBusy(lineId: number): void {
    const next = new Set(busyLines.value);
    next.delete(lineId);
    busyLines.value = next;
}

function formatMoney(minor: number, currency: string): string {
    const v = (minor / 100).toFixed(2);
    return currency.toLowerCase() === 'gbp' ? `£${v}` : `${currency.toUpperCase()} ${v}`;
}

function updateQty(line: CartLine, qty: number): void {
    if (isBusy(line.id)) return;
    markBusy(line.id);
    router.patch(
        `/cart/items/${line.id}`,
        { quantity: qty },
        {
            preserveScroll: true,
            onFinish: () => {
                clearBusy(line.id);
            },
        },
    );
}

function removeLine(line: CartLine): void {
    if (isBusy(line.id)) return;
    markBusy(line.id);
    router.delete(`/cart/items/${line.id}`, {
        preserveScroll: true,
        onFinish: () => {
            clearBusy(line.id);
        },
    });
}

function clearCart(): void {
    router.delete('/cart', { preserveScroll: true });
}

const guestEmail = ref('');
const checkingOut = ref(false);
const hasBlockedLines = computed(() => props.cart.lines.some((l) => !l.ok));

function startCheckout(): void {
    if (checkingOut.value || hasBlockedLines.value) return;
    checkingOut.value = true;
    router.post(
        '/shop/checkout',
        { email: guestEmail.value || undefined },
        {
            preserveScroll: true,
            onFinish: () => {
                checkingOut.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Cart – LTD/EDN" />

    <ShopLayout :show-cart="false">
        <header class="mb-8 flex items-end justify-between gap-4">
            <div>
                <p class="text-[0.625rem] font-bold tracking-widest text-white/60">
                    {{ props.cart.item_count }} {{ props.cart.item_count === 1 ? 'ITEM' : 'ITEMS' }}
                </p>
                <h1 class="mt-1 font-sans text-3xl font-semibold tracking-tight text-white sm:text-4xl">YOUR CART</h1>
            </div>
            <Link href="/shop" class="text-[0.625rem] font-bold tracking-widest text-white/60 hover:text-white"> ← CONTINUE SHOPPING </Link>
        </header>

        <div v-if="props.cart.lines.length === 0" class="border border-white/10 bg-neutral-900/50 p-12 text-center ring-1 ring-white/5">
            <p class="font-mono text-sm tracking-wide text-white/70">Your cart is empty.</p>
            <Link
                href="/shop"
                class="mt-6 inline-flex h-12 items-center justify-center border border-white bg-white px-5 text-sm font-extrabold tracking-wider text-black transition-all hover:bg-neutral-100 active:scale-[0.98]"
            >
                BROWSE SHOP
            </Link>
        </div>

        <div v-else class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-12">
            <section class="space-y-3">
                <article
                    v-for="line in props.cart.lines"
                    :key="line.id"
                    class="border border-white/10 bg-neutral-900/60 p-4 ring-1 ring-white/10 sm:p-5"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-[0.625rem] font-bold tracking-widest text-neutral-500">EDITION</p>
                            <h2 class="mt-1 truncate text-sm font-bold tracking-wider text-white">
                                {{ line.product_name.toUpperCase() }}
                            </h2>
                            <p class="mt-1 text-[0.625rem] font-bold tracking-widest text-white/60">
                                <span v-if="line.sku_code">{{ line.sku_code.toUpperCase() }} · </span
                                >{{ formatMoney(line.unit_amount, props.cart.currency) }} EACH
                            </p>
                            <p v-if="!line.ok && line.error" class="mt-2 text-xs font-bold tracking-wider text-red-400">
                                {{ line.error.toUpperCase() }}
                            </p>
                        </div>
                        <div class="text-right text-sm font-extrabold tracking-tight text-white">
                            {{ formatMoney(line.line_total, props.cart.currency) }}
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div class="inline-flex items-center border border-white/20">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center text-white transition-colors hover:bg-white/10 disabled:opacity-30"
                                :disabled="isBusy(line.id) || line.quantity <= 1"
                                @click="updateQty(line, line.quantity - 1)"
                                aria-label="Decrease quantity"
                            >
                                −
                            </button>
                            <span class="w-10 text-center text-sm font-bold text-white">{{ line.quantity }}</span>
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center text-white transition-colors hover:bg-white/10 disabled:opacity-30"
                                :disabled="isBusy(line.id) || (line.available !== null && line.quantity >= line.available)"
                                @click="updateQty(line, line.quantity + 1)"
                                aria-label="Increase quantity"
                            >
                                +
                            </button>
                        </div>
                        <button
                            type="button"
                            class="text-[0.625rem] font-bold tracking-widest text-white/60 underline decoration-white/40 underline-offset-2 hover:text-white hover:decoration-white disabled:opacity-40"
                            :disabled="isBusy(line.id)"
                            @click="removeLine(line)"
                        >
                            REMOVE
                        </button>
                    </div>
                </article>

                <div class="pt-2">
                    <button type="button" class="text-[0.625rem] font-bold tracking-widest text-white/50 hover:text-white" @click="clearCart">
                        CLEAR CART
                    </button>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="border border-white/10 bg-neutral-900/80 p-5 ring-1 ring-white/10">
                    <p class="text-[0.625rem] font-bold tracking-widest text-neutral-500">ORDER SUMMARY</p>
                    <dl class="mt-4 space-y-2 font-mono text-xs tracking-wide text-white/75">
                        <div class="flex items-center justify-between">
                            <dt>Subtotal</dt>
                            <dd>{{ formatMoney(props.cart.subtotal, props.cart.currency) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-white/50">
                            <dt>Shipping</dt>
                            <dd>Calculated at checkout</dd>
                        </div>
                        <div class="flex items-center justify-between text-white/50">
                            <dt>Tax</dt>
                            <dd>Calculated at checkout</dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-4">
                        <p class="text-[0.625rem] font-bold tracking-widest text-white">TOTAL</p>
                        <p class="text-xl font-extrabold tracking-tight text-white">
                            {{ formatMoney(props.cart.subtotal, props.cart.currency) }}
                        </p>
                    </div>
                </div>

                <div v-if="!isAuthed" class="border border-white/10 bg-neutral-900/60 p-5 ring-1 ring-white/10">
                    <label for="guest-email" class="block text-[0.625rem] font-bold tracking-widest text-white/70"> EMAIL (FOR RECEIPT) </label>
                    <input
                        id="guest-email"
                        v-model="guestEmail"
                        type="email"
                        autocomplete="email"
                        placeholder="you@example.com"
                        class="mt-2 w-full border border-white/20 bg-transparent px-3 py-2 font-mono text-sm text-white placeholder-white/30 focus:border-white/60 focus:outline-none"
                    />
                    <p class="mt-2 text-[0.625rem] font-bold tracking-widest text-white/40">LEAVE BLANK TO ENTER IT AT CHECKOUT</p>
                </div>

                <button
                    type="button"
                    :disabled="checkingOut || hasBlockedLines"
                    class="flex h-14 w-full items-center justify-center gap-2 border border-white bg-white text-sm font-extrabold tracking-wider text-black transition-all hover:bg-neutral-100 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 sm:h-16"
                    @click="startCheckout"
                >
                    <svg v-if="checkingOut" class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                    {{ checkingOut ? 'REDIRECTING…' : 'CHECKOUT' }}
                </button>

                <p class="text-[0.625rem] font-bold tracking-widest text-white/40">SECURE PAYMENT PROCESSING VIA STRIPE</p>
            </aside>
        </div>
    </ShopLayout>
</template>
