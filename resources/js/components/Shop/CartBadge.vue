<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type CartSummary = {
    item_count: number;
    subtotal: number;
    currency: string;
};

const page = usePage();

const summary = computed<CartSummary>(() => {
    const s = (page.props as Record<string, unknown>).cartSummary as CartSummary | undefined;
    return s ?? { item_count: 0, subtotal: 0, currency: 'gbp' };
});
</script>

<template>
    <Link
        href="/cart"
        class="inline-flex items-center gap-2 border border-white/30 px-4 py-2 text-xs font-bold tracking-wider text-white transition-all hover:border-white/60 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293A1 1 0 005.414 17H17M17 17a2 2 0 11-4 0 2 2 0 014 0zm-8 0a2 2 0 11-4 0 2 2 0 014 0z"
            />
        </svg>
        <span>CART</span>
        <span
            v-if="summary.item_count > 0"
            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center bg-white px-1.5 text-[0.625rem] font-extrabold text-black"
        >
            {{ summary.item_count }}
        </span>
    </Link>
</template>
